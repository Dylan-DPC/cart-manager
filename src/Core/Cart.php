<?php

namespace Freshbitsweb\CartManager\Core;

use Freshbitsweb\CartManager\Contracts\CartDriver;
use Illuminate\Contracts\Support\Arrayable;

class Cart implements Arrayable
{
    protected $id = null;

    protected $cartDriver;

    protected $items = [];

    protected $subtotal = 0;

    protected $discount = 0;

    protected $discountPercentage = 0;

    protected $couponId = null;

    protected $shippingCharges = 0;

    protected $netTotal = 0;

    protected $tax = 0;

    protected $total = 0;

    protected $roundOff = 0;

    protected $payable = 0;

    /**
     * Sets object properties
     *
     * @return void
     */
    public function __construct(CartDriver $cartDriver)
    {
        $this->cartDriver = $cartDriver;
        $this->items = collect($this->items);

        if ($cartData = $this->cartDriver->getCartData()) {
            $this->setItems($cartData->items);

            $this->setProperties($cartData->getAttributes());
        }
    }

    /**
     * Sets the object properties from the provided data
     *
     * @param array Cart attributes
     * @return void
     */
    protected function setProperties($attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->{camel_case($key)} = $value;
        }
    }

    /**
     * Creates CartItem objects from the data
     *
     * @param array Cart items data
     * @return void
     */
    protected function setItems($cartItems)
    {
        $cartItems->each(function ($cartItem) {
            $this->items->push(CartItem::createFrom($cartItem->toArray()));
        });
    }

    /**
     * Adds an item to the cart
     *
     * @param Illuminate\Database\Eloquent\Model
     * @return void
     */
    public function add($entity)
    {
        if ($this->itemExists($entity)) {
            $this->updateQuantity($entity);
            $isNewItem = false;
        } else {
            $this->items->push(CartItem::createFrom($entity));
            $isNewItem = true;
        }

        return $this->cartUpdates($isNewItem);
    }

    /**
     * Performs cart updates and returns the data
     *
     * @param boolean Weather its a new item or existing
     * @return json
     */
    protected function cartUpdates($isNewItem = false)
    {
        $this->updateTotals();

        $this->storeCartData($isNewItem);

        return $this->toArray();
    }

    /**
     * Updates the quantity of the cart item
     *
     * @param Illuminate\Database\Eloquent\Model
     * @return void
     */
    protected function updateQuantity($entity)
    {
        $cartItemIndex = $this->items->search($this->cartItemsCheck($entity));

        // Increment quantity of the local ibject
        $this->items[$cartItemIndex]->quantity++;

        // Set new quantity in the cart driver
        $this->cartDriver->setCartItemQuantity(
            $this->items[$cartItemIndex]->id,
            $this->items[$cartItemIndex]->quantity
        );
    }

    /**
     * Checks if an item already exists in the cart
     *
     * @param Illuminate\Database\Eloquent\Model
     * @return boolean
     */
    protected function itemExists($entity)
    {
        return $this->items->contains($this->cartItemsCheck($entity));
    }

    /**
     * Checks if a cart item with the specified entity already exists
     *
     * @param Illuminate\Database\Eloquent\Model
     * @return \Closure
     */
    protected function cartItemsCheck($entity)
    {
        return function ($item) use ($entity) {
            return $item->modelType == get_class($entity) &&
                $item->modelId == $entity->{$entity->getKeyName()}
            ;
        };
    }

    /**
     * Sets the total variables of the object
     *
     * @param boolean Weather to keep the discount in the cart
     * @return void
     */
    protected function updateTotals($keepDiscount = false)
    {
        $this->setSubtotal();

        if (! $keepDiscount) {
            $this->discount = $this->discountPercentage = 0;
            $this->couponId = null;
        }

        $this->setShippingCharges();

        $this->netTotal = round($this->subtotal - $this->discount + $this->shippingCharges, 2);

        $this->tax = round(($this->netTotal * config('cart_manager.tax_percentage')) / 100, 2);

        $this->total = round($this->netTotal + $this->tax, 2);

        $this->setPayableAndRoundOff();
    }

    /**
     * Sets the subtotal of the cart
     *
     * @return void
     */
    protected function setSubtotal()
    {
        $this->subtotal = round($this->items->sum(function ($cartItem) {
            return $cartItem->price * $cartItem->quantity;
        }), 2);
    }

    /**
     * Sets the shipping charges of the cart
     *
     * @return void
     */
    protected function setShippingCharges()
    {
        $this->shippingCharges = 0;
        $orderAmount = $this->subtotal - $this->discount;

        if ($orderAmount > 0 && $orderAmount < config('cart_manager.shipping_charges_threshold')) {
            $shippingCharges = config('cart_manager.shipping_charges');

            if ($shippingCharges > 0) {
                $this->shippingCharges = $shippingCharges;
            }
        }
    }

    /**
     * Sets the payable and round off amount of the cart
     *
     * @return void
     */
    protected function setPayableAndRoundOff()
    {
        switch (config('cart_manager.round_off_to')) {
            case 0.05:
                // https://stackoverflow.com/a/1592379/3113599
                $this->payable = round($this->total * 2, 1) / 2;
                break;

            case 0.1:
                $this->payable = round($this->total, 1);
                break;

            case 0.5:
                // http://www.kavoir.com/2012/10/php-round-to-the-nearest-0-5-1-0-1-5-2-0-2-5-etc.html
                $this->payable = round($this->total * 2) / 2;
                break;

            case 1:
                $this->payable = round($this->total);
                break;

            default:
                $this->payable = $this->total;
        }

        $this->roundOff = round($this->total - $this->payable, 2);
    }

    /**
     * Stores the cart data on the cart driver
     *
     * @param boolean Weather its a new item or existing
     * @return void
     */
    protected function storeCartData($isNewItem = false)
    {
        if ($this->id) {
            $this->cartDriver->updateCart($this->toArray($withItems = false));

            if ($isNewItem) {
                $this->cartDriver->addCartItem($this->id, $this->items->last()->toArray());
            }

            return;
        }

        $this->cartDriver->storeNewCartData($this->toArray());
    }

    /**
     * Returns object properties as array
     *
     * @param boolean Weather items should also be covered
     * @return array
     */
    public function toArray($withItems = true)
    {
        $cartData = [
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'discount_percentage' => $this->discountPercentage,
            'coupon_id' => $this->couponId,
            'shipping_charges' => $this->shippingCharges,
            'net_total' => $this->netTotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'round_off' => $this->roundOff,
            'payable' => $this->payable,
        ];

        if ($this->id) {
            $cartData['id'] = $this->id;
        }

        if ($withItems) {
            // First toArray() for CartItem object and second one for the Illuminate Collection
            $cartData['items'] = $this->items->map->toArray()->toArray();
        }

        return $cartData;
    }

    /**
     * Removes an item from the cart
     *
     * @param int index of the item
     * @return json
     */
    public function removeAt($cartItemIndex)
    {
        $this->cartDriver->removeCartItem($this->items[$cartItemIndex]->id);
        $this->items = $this->items->forget($cartItemIndex)->values(); // To reset the index

        return $this->cartUpdates();
    }

    /**
     * Increments the quantity of a cart item
     *
     * @param int Index of the cart item
     * @return json
     */
    public function incrementQuantityAt($cartItemIndex)
    {
        $this->items[$cartItemIndex]->quantity++;

        $this->cartDriver->setCartItemQuantity(
            $this->items[$cartItemIndex]->id,
            $this->items[$cartItemIndex]->quantity
        );

        return $this->cartUpdates();
    }

    /**
     * Increments the quantity of a cart item
     *
     * @param int Index of the cart item
     * @return json
     */
    public function decrementQuantityAt($cartItemIndex)
    {
        if ($this->items[$cartItemIndex]->quantity == 1) {
            return $this->removeAt($cartItemIndex);
        }

        $this->items[$cartItemIndex]->quantity--;

        $this->cartDriver->setCartItemQuantity(
            $this->items[$cartItemIndex]->id,
            $this->items[$cartItemIndex]->quantity
        );

        return $this->cartUpdates();
    }
}
