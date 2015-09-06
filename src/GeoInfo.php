<?php

namespace DevGroup\Multilingual;

use Yii;

class GeoInfo
{
    /** @var null|Country */
    public $country = null;
    /** @var null|City */
    public $city = null;
    /** @var null|Region */
    public $region = null;

    /** @var string User's IP */
    public $ip = null;

    public function __construct($config=[])
    {
        $this->country = $this->configure(new Country(), $config, 'country' );
        $this->city    = $this->configure(new City(),    $config, 'city'    );
        $this->region  = $this->configure(new Region(),  $config, 'region'  );
    }

    private function configure($object, $config, $configAttribute)
    {
        if (isset($config[$configAttribute]) === false) {
            return $object;
        }
        foreach ($config[$configAttribute] as $key => $value) {
            if (property_exists($object, $key)) {
                $object->$key = $value;
            }
        }
        return $object;
    }
}