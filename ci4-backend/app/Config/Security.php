<?php

namespace Config;

class Security extends \CodeIgniter\Config\BaseConfig
{
    public $csrfProtection = 'cookie';
    public $tokenRandomize = true;
    public $salt = 'your-csrf-salt-here';
    public $csrfExpire = 7200;
    public $csrfRegenerate = true;
    public $csrfExcludeURIs = ['api/*'];
    public $csrfRedirect = false;
    
    public $allowedUploadExtensions = ['pdf'];
    public $maxUploadSize = 10485760; // 10MB
    
    public $sessionDriver = 'CodeIgniter\Session\Handlers\FileHandler';
    public $sessionCookieName = 'ci_session';
    public $sessionExpiration = 7200;
    public $sessionSavePath = WRITEPATH . 'session';
    public $sessionMatchIP = false;
    public $sessionTimeToUpdate = 300;
    public $sessionRegenerateDestroy = false;
}
