<?php declare(strict_types=1);
/**
 * CrowdSec_Bouncer Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT LICENSE
 * that is bundled with this package in the file LICENSE
 *
 * @category   CrowdSec
 * @package    CrowdSec_Bouncer
 * @copyright  Copyright (c)  2021+ CrowdSec
 * @author     CrowdSec team
 * @see        https://crowdsec.net CrowdSec Official Website
 * @license    MIT LICENSE
 *
 */

/**
 *
 * @category CrowdSec
 * @package  CrowdSec_Bouncer
 * @module   Bouncer
 * @author   CrowdSec team
 *
 */

namespace CrowdSec\Bouncer\Observer;

use LogicException;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use CrowdSec\Bouncer\Event\Event;
use CrowdSec\Bouncer\Event\EventInterface;

class Order extends Event implements EventInterface, ObserverInterface
{
    /**
     * Get event data
     *
     * @param array $objects
     * @return array|string[]
     */
    public function getEventData(array $objects = []): array
    {
        $order = $objects['order'] ?? null;

        return $order ?
            [
                'order_id' => (string) $order->getIncrementId(),
                'customer_id' => (string) $order->getCustomerId(),
                'quote_id' => (string) $order->getQuoteId(),
            ] : [];
    }

    /**
     * Event observer execution
     *
     * @param Observer $observer
     * @return $this
     * @throws LogicException
     */
    public function execute(Observer $observer): Order
    {
        if ($this->helper->isEventsLogEnabled($this->process)) {
            $order = $observer->getOrder();
            $baseData = $this->getBaseData();
            $dataObjects = ['order' => $order];
            $eventData = $this->getEventData($dataObjects);
            $finalData = array_merge($baseData, $eventData);
            if ($this->validateEvent($finalData)) {
                $this->helper->getEventLogger()->info('', $finalData);
            }
        }

        return $this;
    }
}
