<?php
namespace dowleydeveloped\cookieconsent\twigextensions;

use dowleydeveloped\cookieconsent\CookieConsent;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class CookieExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * Returns the globals to add.
     */
    public function getGlobals(): array
    {
        return [
            "dowleycookie" => CookieConsent::$cookieVariable,
        ];
    }
}
