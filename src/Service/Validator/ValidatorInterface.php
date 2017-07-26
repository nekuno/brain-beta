<?php

interface ValidatorInterface {

    public function validateOnCreate($data);

    public function validateOnUpdate($data);

    public function validateOnDelete($userId);

}
