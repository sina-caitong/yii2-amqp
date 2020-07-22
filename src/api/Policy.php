<?php

namespace pzr\amqp\api;

use InvalidArgumentException;

class Policy extends AmqpApi
{

    protected $name;
    protected $priority;
    protected $pattern;
    protected $applyTo;
    protected $definition;

    protected $policyConfig;

    public function getPolicy()
    {
        $reqUrl = $this->formatApiUrl('policies', $this->name);
        $data = self::$http->request('GET', $reqUrl)->getContent();
        return json_decode($data, true);
    }

    /**
     *  https://honeypps.com/mq/rabbitmq-mirror-queue/
     *
     * @param [type] $mirrorName
     * @param integer $priority
     * @param string $vhost
     * @return void
     */
    public function setPolicy()
    {
        $reqUrl = $this->formatApiUrl($this->apiName,  $this->name);
        $post = [
            'pattern' => $this->pattern,
            'definition' => $this->definition,
            'priority' => $this->priority,
            'apply-to' => $this->applyTo,
            'name' => $this->name,
        ];
        $options = $this->getHttpOptions(['body' => json_encode($post)]);
        $data = self::$http->request('PUT', $reqUrl, $options)->getContent();
        return $data;
    }

    public function setPolicyConfig($policyConfig)
    {
        // 校验数据的合法性
        isset($policyConfig['pattern'])  ? $this->pattern = $policyConfig['pattern']  : $this->handleError('pattern');
        isset($policyConfig['name'])     ? $this->name = $policyConfig['name']        : $this->handleError('name');
        isset($policyConfig['apply-to']) ? $this->applyTo = $policyConfig['apply-to'] : $this->handleError('apply-to');
        $this->priority = isset($policyConfig['priority']) && is_int($policyConfig['priority']) ? $policyConfig['priority'] : 0;

        if (!in_array($this->applyTo, ['all', 'queues', 'exchanges'])) {
            throw new InvalidArgumentException('unexcept value about apply-to, the value defined by one of [all, queues, exchanges]');
        }
        if (!isset($policyConfig['definition'])) {
            $this->definiton = [
                'ha-mode' => 'manual',
                'ha-sync-mode' => 'all',
            ];
            return;
        }
        $definition = $policyConfig['definition'];
        $haMode = $definition['ha-mode'];
        $haSyncMode = $definition['ha-sync-mode'];
        if (!in_array($haMode, ['all', 'exactly', 'nodes'])) {
            throw new InvalidArgumentException('unexcept value about ha-mode, the value defined by one of [all, exactly, nodes]');
        }

        if (!in_array($haSyncMode, ['manual', 'automatic'])) {
            throw new InvalidArgumentException('unexcept value about ha-sync-mode, the value defined by one of [manual, automatic]');
        }

        // 指定镜像的数量，需要配置ha-params
        if ($haMode === 'exactly' && (!isset($definition['ha-params']) || !is_int($definition['ha-params']))) {
            $this->handleError('ha-params');
        }

        if ($haMode === 'nodes' && !isset($definition['ha-params'])) {
            $this->handleError('ha-params');
        }

        $this->definition = $definition;
        return $this;
    }
}
