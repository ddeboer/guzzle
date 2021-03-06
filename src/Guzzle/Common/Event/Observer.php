<?php
/**
 * @package Guzzle PHP <http://www.guzzlephp.org>
 * @license See the LICENSE file that was distributed with this source code.
 */

namespace Guzzle\Common\Event;

/**
 * Guzzle Observer class
 *
 * @author Michael Dowling <michael@guzzlephp.org>
 */
interface Observer
{
    /**
     * Receive notifications from a EventManager
     *
     * @param Subject $subject Subject emitting the event
     * @param string $event Event signal state
     * @param mixed $context (optional) Contextual information
     *
     * @return null|bool|mixed
     */
    public function update(Subject $subject, $event, $context = null);
}