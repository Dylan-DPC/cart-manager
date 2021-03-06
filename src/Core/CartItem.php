<?php

namespace Freshbitsweb\CartManager\Core;

use Freshbitsweb\CartManager\Exceptions\ItemNameMissing;
use Freshbitsweb\CartManager\Exceptions\ItemPriceMissing;
use Illuminate\Contracts\Support\Arrayable;

class CartItem implements Arrayable
{
    public $id = null;

    public $modelType;

    public $modelId;

    public $name;

    public $price;

    public $quantity = 1;

    /**
     * Creates a new cart item
     *
     * @param Illuminate\Database\Eloquent\Model|array
     * @return \Freshbitsweb\CartManager\Core\CartItem
     */
    public function __construct($entity)
    {
        if (is_array($entity)) {
            return $this->createFromArray($entity);
        }

        return $this->createFromModel($entity);
    }

    /**
     * Creates a new cart item from a model instance
     *
     * @param Illuminate\Database\Eloquent\Model
     * @return \Freshbitsweb\CartManager\Core\CartItem
     */
    protected function createFromModel($entity)
    {
        $this->modelType = get_class($entity);
        $this->modelId = $entity->{$entity->getKeyName()};
        $this->setName($entity);
        $this->setPrice($entity);

        return $this;
    }

    /**
     * Creates a new cart item from an array
     *
     * @param array
     * @return \Freshbitsweb\CartManager\Core\CartItem
     */
    protected function createFromArray($array)
    {
        $this->id = $array['id'];
        $this->modelType = $array['model_type'];
        $this->modelId = $array['model_id'];
        $this->name = $array['name'];
        $this->price = $array['price'];
        $this->quantity = $array['quantity'];

        return $this;
    }

    /**
     * Creates a new cart item from an array or entity
     *
     * @param Illuminate\Database\Eloquent\Model|array
     * @return \Freshbitsweb\CartManager\Core\CartItem
     */
    public static function createFrom($array)
    {
        return new static($array);
    }

    /**
     * Sets the name of the item
     *
     * @param Illuminate\Database\Eloquent\Model
     * @return void
     * @throws ItemNameMissing
     */
    protected function setName($entity)
    {
        if (method_exists($entity, 'getName')) {
            $this->name = $entity->getName();
            return;
        }

        if (property_exists($entity, 'name')) {
            $this->name = $entity->name;
            return;
        }

        throw ItemNameMissing::for($this->modelType);
    }

    /**
     * Sets the price of the item
     *
     * @param Illuminate\Database\Eloquent\Model
     * @return void
     * @throws ItemPriceMissing
     */
    protected function setPrice($entity)
    {
        if (method_exists($entity, 'getPrice')) {
            $this->price = $entity->getPrice();
            return;
        }

        if (isset($entity->price)) {
            $this->price = $entity->price;
            return;
        }

        throw ItemPriceMissing::for($this->modelType);
    }

    /**
     * Returns object properties as array
     *
     * @return array
     */
    public function toArray()
    {
        $cartItemData = [
            'model_type' => $this->modelType,
            'model_id' => $this->modelId,
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
        ];

        if ($this->id) {
            $cartItemData['id'] = $this->id;
        }

        return $cartItemData;
    }
}
