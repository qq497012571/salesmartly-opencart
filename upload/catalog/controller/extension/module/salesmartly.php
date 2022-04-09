<?php

class ControllerExtensionModuleSaleSmartly extends Controller
{
    protected $error = array();

    const SCRIPT_PATTERN =
        '<script>
            var salesmartlyName = "%s";
            var salesmartlyEmail = "%s";
            var salesmartlyPhone = "%s";
         </script>';

    const STATUS = 'salesmartly_status';
    const SCRIPT = 'salesmartly_script';

    public function header(&$route, &$args, &$output)
    {
        if ($this->config->get(self::STATUS)) {
            $salesmartlyName = '';
            $salesmartlyEmail = '';
            $salesmartlyPhone = '';

            if ($this->customer->isLogged()) {
                $salesmartlyName = $this->customer->getFirstName() . ' ' . $this->customer->getLastName();
                $salesmartlyEmail = $this->customer->getEmail();
                $salesmartlyPhone = $this->customer->getTelephone();
            }

            $args['analytics'][] = sprintf(
                self::SCRIPT_PATTERN,
                $salesmartlyName,
                $salesmartlyEmail,
                $salesmartlyPhone,
            );
        }
    }

    public function footer(&$route, &$args, &$output)
    {
        if ($this->config->get(self::STATUS)) {
            $output = $this->config->get(self::SCRIPT);
        }
    }
    
}