<?php

class TestableEmailsubscription extends Ps_Emailsubscription
{
    public function callUnregister($email, $registerStatus)
    {
        return $this->unregister($email, $registerStatus);
    }

    public function readOriginNewsletter()
    {
        return $this->_origin_newsletter;
    }

    public function setOriginNewsletter($value)
    {
        $this->_origin_newsletter = $value;
    }
}
