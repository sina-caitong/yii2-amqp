<?php


namespace pzr\amqp\tests;

class Obj
{

    protected $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function test()
    {
        return 'this is a test, name is ' . $this->name;
    }

    public static function staticTest()
    {
        return 'this is a static test';
    }
}