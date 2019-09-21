<?php

namespace App\Service;

use App\MainBundle\Document\Order;
use App\MainBundle\Document\ShoppingCart;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment as TwigEnvironment;

class ShopCartService
{
    /** @var ContainerInterface */
    protected $container;

    /** @param ContainerInterface $container */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $type
     * @return array|null
     */
    public function getContent($type = 'shop')
    {
        $shoppingCart = $this->getShoppingCartByType($type);
        return $shoppingCart ? $shoppingCart->getContentSorted() : [];
    }

    /**
     * Clear shopping cart data
     * @param string $type
     * @return bool
     */
    public function clearContent($type = 'shop')
    {
        /** @var \Doctrine\ODM\MongoDB\DocumentManager $dm */
        $dm = $this->container->get('doctrine_mongodb')->getManager();

        $shoppingCart = $this->getShoppingCartByType($type);
        if ($shoppingCart) {
            $dm->remove($shoppingCart);
            $dm->flush();
            return true;
        }
        return false;
    }

    /**
     * @param string $type
     * @return ShoppingCart|null
     */
    public function getShoppingCartByType($type)
    {
        return $this->getShoppingCart($type, $this->getUserId(), $this->getSessionId());
    }

    /**
     * @param string $type
     * @param null|int $userId
     * @param null|string $sessionId
     * @param null|int $id
     * @param bool $create
     * @return ShoppingCart|null
     */
    public function getShoppingCart($type, $userId = null, $sessionId = null, $id = null, $create = false)
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('app.settings');
        $currency = $settingsService->getCurrency();
        $lifeTime = $this->container->hasParameter('app.shopping_cart_lifetime')
            ? (int) $this->container->getParameter('app.shopping_cart_lifetime')
            : 172800;// 48 hours

        if ($id) {
            $shoppingCart = $this->getRepository()->find((int) $id);
        } else if ($userId || $sessionId || $id) {
            $shoppingCart = $this->getRepository()->findByUserOrSession($type, $userId, $sessionId);
        } else {
            return null;
        }
        if (!$shoppingCart && $create) {
            /** @var \Doctrine\ODM\MongoDB\DocumentManager $dm */
            $dm = $this->container->get('doctrine_mongodb')->getManager();

            $shoppingCart = new ShoppingCart();
            $shoppingCart
                ->setSessionId($sessionId)
                ->setOwnerId($userId)
                ->setCurrency($currency)
                ->setType($type);

            if ($lifeTime) {
                $shoppingCart->setExpiresOn((new \DateTime())->setTimestamp(time() + $lifeTime));
            }

            $dm->persist($shoppingCart);
            $dm->flush();
        }
        return $shoppingCart;
    }

    /**
     * @param ShoppingCart|null $shoppingCart
     * @param string $templateName
     * @param string $type
     * @return string
     */
    public function renderShopCart($shoppingCart, $templateName, $type = 'shop')
    {
        /** @var TwigEnvironment $twig */
        $twig = $this->container->get('twig');

        $output = $twig->getRuntime(\App\Twig\AppRuntime::class)
            ->shopCartFunction($twig, $templateName, $type, '', $shoppingCart);

        return $output;
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        if (!empty($_COOKIE[ShoppingCart::SESSION_KEY])) {
            return $_COOKIE[ShoppingCart::SESSION_KEY];
        }
        $sessionId = UtilsService::generatePassword(26);
        $lifeTime = $this->container->hasParameter('app.shopping_cart_lifetime')
            ? (int) $this->container->getParameter('app.shopping_cart_lifetime')
            : 172800;// 48 hours
        setcookie(ShoppingCart::SESSION_KEY, $sessionId, time() + $lifeTime, '/');
        return $sessionId;
    }

    /**
     * @return string
     */
    public static function getCurrencyCookie()
    {
        return isset($_COOKIE['shkCurrency'])
            ? $_COOKIE['shkCurrency']
            : '';
    }

    /**
     * @param Order $order
     * @return array
     */
    public function createReceipt(Order $order) {
        $receipt = [];
        switch ($order->getPaymentValue()) {
            case 'RoboKassa':
                if ($this->container->getParameter('app.tax_system')) {
                    $receipt['sno'] = $this->container->getParameter('app.tax_system');
                }
                $receipt['items'] = $order->getReceipt(
                    [
                        'priceName' => 'sum'
                    ],
                    [
                        'payment_method' => $this->container->hasParameter('app.payment_method')
                            ? $this->container->getParameter('app.payment_method')
                            : 'full_prepayment',
                        'payment_object' => $this->container->hasParameter('app.payment_object')
                            ? $this->container->getParameter('app.payment_object')
                            : 'commodity',
                        'tax' => $this->container->getParameter('app.nds_rate')
                    ],
                    [
                        'payment_method' => $this->container->hasParameter('app.payment_method')
                            ? $this->container->getParameter('app.payment_method')
                            : 'full_prepayment',
                        'payment_object' => 'service',
                        'tax' => 'none'
                    ]
                );
                break;
            default:// YandexMoney
                $receipt['customerContact'] = $order->getPhone()
                    ? preg_replace("/[^\d]/", '', $order->getPhone())
                    : $order->getEmail();
                $receipt['items'] = $order->getReceipt(
                    [
                        'priceName' => 'price.amount',
                        'titleName' => 'text'
                    ],
                    [
                        'tax' => $this->container->getParameter('app.nds_rate')
                    ],
                    [
                        'tax' => 1
                    ]
                );
                if ($this->container->getParameter('app.tax_system')) {
                    $receipt['taxSystem'] = $this->container->getParameter('app.tax_system');
                }
                break;
        }
        return $receipt;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        if ($user = $this->getUser()) {
            return $user->getId();
        }
        return 0;
    }

    /**
     * @return mixed
     */
    protected function getUser()
    {
        if (!$this->container->has('security.token_storage')) {
            throw new \LogicException('The SecurityBundle is not registered in your application. Try running "composer require symfony/security-bundle".');
        }
        if (null === $token = $this->container->get('security.token_storage')->getToken()) {
            return null;
        }
        if (!\is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return null;
        }
        return $user;
    }

    /**
     * @return \App\Repository\ShoppingCartRepository
     */
    public function getRepository()
    {
        return $this->container->get('doctrine_mongodb')
            ->getManager()
            ->getRepository(ShoppingCart::class);
    }

    /**
     * @param mixed $document
     * @param string $fieldName
     * @param string $locale
     * @param string $localeDefault
     * @return string
     */
    public static function getTranslatedField($document, $fieldName, $locale, $localeDefault)
    {
        $translations = $document['translations'] ?? [];
        if ($locale !== $localeDefault
            && isset($translations[$fieldName])
            && isset($translations[$fieldName][$locale])) {
                return $translations[$fieldName][$locale];
        }
        return $document[$fieldName] ?? '';
    }
}
