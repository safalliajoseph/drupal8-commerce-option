<?php

namespace Drupal\commerce_option\EventSubscriber;

use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_price\Calculator;

use Drupal\Core\TempStore\PrivateTempStoreFactory;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class CartEventSubscriber.
 */
class CartEventSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\TempStore\PrivateTempStoreFactory definition.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

  /**
   * Constructs a new CartEventSubscriber object.
   */
  public function __construct(PrivateTempStoreFactory $tempstore_private) {
    $this->tempStore = $tempstore_private;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[CartEvents::CART_ENTITY_ADD][] = ['addOptions'];
    $events[CartEvents::CART_ENTITY_ADD][] = ['mergeOrderItems'];

    return $events;
  }

  /**
   * Adds the option values to the new order item.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function addOptions(Event $event) {
    $variation_id = $this->tempStore->get('commerce_option')->get('variation_id');
    $options = $this->tempStore->get('commerce_option')->get('options');

    if (!$variation_id || !$options) {
      return;
    }

    $orderItem = $event->getOrderItem();
    $purchasedEntity = $orderItem->getPurchasedEntity();
    if ($purchasedEntity->id() !== $variation_id) {
      return;
    }

    foreach ($options as $option) {
      $orderItem->field_options[] = ['target_id' => $option];
    }

    $orderItem->save();

    $this->tempStore->get('commerce_option')->delete('variation_id');
    $this->tempStore->get('commerce_option')->delete('options');
  }

  /**
   * Merges order items if they have the same option values.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function mergeOrderItems(Event $event) {
    $existingOrderItems = $event->getCart()->getItems();

    $orderItem = $event->getOrderItem();
    $optionsItemList = $orderItem->field_options;
    $cart = $event->getCart();
    foreach ($existingOrderItems as $existingOrderItem) {
      // Skip order item we just added.
      if ($existingOrderItem->id() === $orderItem->id()) {
        continue;
      }

      if (!$existingOrderItem->field_options->equals($optionsItemList)) {
        return;
      }

      $newQuantity = Calculator::add($orderItem->getQuantity(), $existingOrderItem->getQuantity());
      $existingOrderItem->setQuantity($newQuantity);
      $existingOrderItem->save();
      $cart->removeItem($orderItem);
      $orderItem->delete();
    }
  }

}
