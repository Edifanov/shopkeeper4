<?php

namespace AppBundle\Twig;

use AppBundle\Controller\CatalogController;
use AppBundle\Document\Category;
use AppBundle\Document\Setting;
use AppBundle\Service\SettingsService;
use AppBundle\Service\UtilsService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\HttpFoundation\RequestStack;

class AppExtension extends AbstractExtension
{
    /** @var ContainerInterface */
    protected $container;
    /** @var  RequestStack */
    protected $requestStack;
    /** @var array */
    protected $cache = [];

    /** @param ContainerInterface $container */
    public function __construct(ContainerInterface $container, RequestStack $requestStack)
    {
        $this->container = $container;
        $this->requestStack = $requestStack;
    }

    public function getFilters()
    {
        return [
            new TwigFilter('price', [AppRuntime::class, 'priceFilter'])
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('catalogPath', array($this, 'catalogPathFunction')),
            new TwigFunction('outputFilter', [$this, 'outputFilterFunction'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
            new TwigFunction('getFieldOption', [$this, 'getFieldOptionFunction']),
            new TwigFunction('renderOutputType', [$this, 'renderOutputTypeFunction'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
            new TwigFunction('renderOutputTypeArray', [$this, 'renderOutputTypeArrayFunction'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
            new TwigFunction('renderOutputTypeField', [$this, 'renderOutputTypeFieldFunction'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
            new TwigFunction('renderOutputTypeChunk', [$this, 'renderOutputTypeChunkFunction'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
            new TwigFunction('categoriesTree', [$this, 'categoriesTreeFunction'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
            new TwigFunction('twigNextPass', [$this, 'twigNextPassFunction']),
            new TwigFunction('shopCart', [AppRuntime::class, 'shopCartFunction'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
            new TwigFunction('currencyList', [AppRuntime::class, 'currencyListFunction'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
            new TwigFunction('imageUrl', [AppRuntime::class, 'imageUrlFunction']),
            new TwigFunction('contentList', [AppRuntime::class, 'contentListFunction'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ])
        ];
    }

    /**
     * @param string $parentUri
     * @param string $systemName
     * @param array $itemData
     * @return string
     */
    public function catalogPathFunction($parentUri = '', $systemName = '', $itemData = [])
    {
        $baseUrl = $this->container->get('router')->getContext()->getBaseUrl();
        $path = $baseUrl;

        if (!$parentUri && !$systemName) {
            $parentId = isset($itemData['parentId']) ? $itemData['parentId'] : 0;
            if ($parentId) {
                if (isset($this->cache['categoryUriData'][$parentId])) {
                    $parentUri = $this->cache['categoryUriData'][$parentId]['uri'];
                    $systemNameField = isset($this->cache['categoryUriData'][$parentId]['systemNameField'])
                        ? $this->cache['categoryUriData'][$parentId]['systemNameField']
                        : '';
                    $systemName = $systemNameField && !empty($itemData[$systemNameField])
                        ? $itemData[$systemNameField]
                        : '';
                } else {
                    /** @var \Doctrine\ODM\MongoDB\DocumentManager $dm */
                    $dm = $this->container->get('doctrine_mongodb')->getManager();
                    $categoryRepository = $dm->getRepository(Category::class);
                    /** @var Category $category */
                    $category = $categoryRepository->findOneBy([
                        'id' => $parentId,
                        'isActive' => true
                    ]);
                    $systemNameField = '';
                    if ($category) {
                        $parentUri = $category->getUri();
                        $systemNameField = $category->getContentType()->getSystemNameField();
                        $systemName = $systemNameField && !empty($itemData[$systemNameField])
                            ? $itemData[$systemNameField]
                            : '';
                    }
                    if (!isset($this->cache['categoryUriData'])) {
                        $this->cache['categoryUriData'] = [];
                    }
                    $this->cache['categoryUriData'][$parentId] = [
                        'uri' => $parentUri,
                        'systemNameField' => $systemNameField
                    ];
                }
            }
        }
        if ($parentUri) {
            $path .= '/' . $parentUri;
        }
        $path .= $systemName;
        return $path;
    }

    /**
     * @param $fieldsData
     * @param $fieldName
     * @param string $optionName
     * @return string|array
     */
    public function getFieldOptionFunction($fieldsData, $fieldName, $optionName = 'type')
    {
        $index = array_search($fieldName, array_column($fieldsData, 'name'));
        if ($index === false) {
            return '';
        }
        return isset($fieldsData[$index][$optionName]) ? $fieldsData[$index][$optionName] : '';
    }

    /**
     * @param \Twig_Environment $environment
     * @param $value
     * @param $type
     * @param array $properties
     * @param array $options
     * @return mixed
     */
    public function renderOutputTypeFunction(\Twig_Environment $environment, $value, $type, $properties = [], $options = [])
    {
        if (empty($value)) {
            $value = '';
        }
        $chunkName = !empty($properties['chunkName']) ? $properties['chunkName'] : $type;
        $chunkNamePrefix = !empty($properties['chunkNamePrefix'])
            ? $properties['chunkNamePrefix']
            : '';
        if (is_array($value)) {
            $properties = array_merge($properties, $options, ['value' => '', 'data' => $value]);
        } else {
            $properties = array_merge($properties, $options, ['value' => $value]);
        }
        if (!isset($properties['systemNameField'])) {
            $properties['systemNameField'] = '';
        }
        if (!isset($properties['currentCategoryUri'])) {
            $properties['currentCategoryUri'] = '';
        }
        $properties['systemName'] = !empty($options[$properties['systemNameField']])
            ? $options[$properties['systemNameField']]
            : '';
        if (!empty($value)) {
            $templateName = $this->getTemplateName(
                $environment,
                'chunks/fields/',
                $chunkName,
                $chunkNamePrefix,
                '',
                'text'
            );
        } else {
            $templateName = $this->getTemplateName(
                $environment,
                'chunks/fields/',
                $chunkName,
                $chunkNamePrefix,
                '_empty',
                'empty'
            );
        }
        return $environment->render($templateName, $properties);
    }

    /**
     * @param \Twig_Environment $environment
     * @param $itemData
     * @param $fieldsData
     * @param string $chunkNamePrefix
     * @return string
     */
    public function renderOutputTypeArrayFunction(\Twig_Environment $environment, $itemData, $fieldsData, $chunkNamePrefix = '')
    {
        if (empty($itemData)) {
            return '';
        }
        $output = '';
        foreach ($fieldsData as $field) {
            if (!isset($itemData[$field['name']])) {
                continue;
            }
            $output .= $this->renderOutputTypeFunction(
                $environment,
                $itemData[$field['name']],
                $field['type'],
                array_merge($field['properties'], ['chunkNamePrefix' => $chunkNamePrefix]),
                $itemData
            );
        }
        return $output;
    }

    /**
     * @param \Twig_Environment $environment
     * @param $filtersData
     * @param string $chunkNamePrefix
     * @return string
     */
    public function outputFilterFunction(\Twig_Environment $environment, $filtersData, $chunkNamePrefix = '')
    {
        if (empty($filtersData)) {
            return '';
        }
        $templateName = $this->getTemplateName($environment, 'chunks/filters/', $filtersData['outputType'], $chunkNamePrefix);
        return $environment->render($templateName, ['filter' => $filtersData]);
    }

    /**
     * @param \Twig_Environment $environment
     * @param $itemData
     * @param $fieldsData
     * @param $chunkName
     * @param string $chunkNamePrefix
     * @return string
     */
    public function renderOutputTypeChunkFunction(\Twig_Environment $environment, $itemData, $fieldsData, $chunkName, $chunkNamePrefix = '')
    {
        $chunkNamesArr = array_column(array_column($fieldsData, 'properties'), 'chunkName');
        $index = array_search($chunkName, $chunkNamesArr);
        if ($index === false) {
            return '';
        }
        $field = $fieldsData[$index];
        $value = '';
        if (isset($itemData[$field['name']])) {
            $value = $itemData[$field['name']];
        }
        $propertiesDefault = [
            'fieldData' => [
                'name' => $field['name'],
                'title' => $field['title'],
                'description' => $field['description']
            ]
        ];
        if (is_array($value)) {
            $propertiesDefault['value'] = '';
            $propertiesDefault['data'] = $value;
        } else {
            $propertiesDefault['value'] = $value;
        }
        $properties = array_merge($field['properties'], $propertiesDefault);
        $properties['systemName'] = !empty($itemData[$properties['systemNameField']])
            ? $itemData[$properties['systemNameField']]
            : '';
        if (!empty($value)) {
            $templateName = $this->getTemplateName($environment, 'chunks/fields/', $chunkName, $chunkNamePrefix);
        } else {
            $templateName = $this->getTemplateName(
                $environment,
                'chunks/fields/',
                $chunkName,
                $chunkNamePrefix,
                '_empty',
                'empty'
            );
        }
        return $environment->render($templateName, $properties);
    }

    /**
     * @param \Twig_Environment $environment
     * @param $itemData
     * @param $fieldsData
     * @param $fieldName
     * @return mixed|string
     */
    public function renderOutputTypeFieldFunction(\Twig_Environment $environment, $itemData, $fieldsData, $fieldName)
    {
        $outputType = $this->getFieldOptionFunction($fieldsData, $fieldName, 'type');
        $outputTypeProperties = $this->getFieldOptionFunction($fieldsData, $fieldName, 'properties');
        if (empty($outputTypeProperties['chunkName'])) {
            return '';
        }
        return $this->renderOutputTypeFunction(
            $environment,
            $itemData[$fieldName],
            $outputType,
            $outputTypeProperties,
            $itemData
        );
    }

    /**
     * @param \Twig_Environment $environment
     * @param int $parentId
     * @param string $chunkName
     * @param null $data
     * @param bool $cacheEnabled
     * @return string
     */
    public function categoriesTreeFunction(\Twig_Environment $environment, $parentId = 0, $chunkName = 'menu_tree', $data = null, $cacheEnabled = false)
    {
        $request = $request = $this->requestStack->getCurrentRequest();
        $currentUri = substr($request->getPathInfo(), 1);
        $uriArr = UtilsService::getUriArray($currentUri);

        $cacheKey = 'tree.' . $chunkName;
        /** @var FilesystemCache $cache */
        $cache = $this->container->get('app.filecache');

        if ($data === null) {
            if ($cacheEnabled && $cache->has($cacheKey)) {
                return $environment->createTemplate($cache->get($cacheKey))->render([
                    'currentUri' => $currentUri,
                    'uriArr' => $uriArr
                ]);
            }
            $catalogController = new CatalogController();
            $catalogController->setContainer($this->container);
            $categoriesTree = $catalogController->getCategoriesTree($parentId);
            $data = $categoriesTree[0];
        }
        $templateName = $this->getTemplateName($environment, 'nav/', $chunkName);
        if (empty($data['children'])) {
            return '';
        }
        $data['currentUri'] = $currentUri;
        $data['uriArr'] = $uriArr;
        $output = $environment->render($templateName, $data);
        if (!$cacheEnabled) {
            return $output;
        }

        $cache->set($cacheKey, $output, 60*60*24);

        return $environment->createTemplate($output)->render([
            'currentUri' => $currentUri,
            'uriArr' => $uriArr
        ]);
    }

    /**
     * @param string $string
     * @return mixed|string
     */
    public function twigNextPassFunction($string)
    {
        $vars = func_get_args();
        array_splice($vars, 0, 1);
        $output = '{% '. $string . ' %}';
        foreach ($vars as $ind => $var) {
            $output = str_replace('$'.($ind+1), "'{$var}'", $output);
        }
        return str_replace('$', '', $output);
    }

    /**
     * @param \Twig_Environment $environment
     * @param $path
     * @param $chunkName
     * @param string $chunkNamePrefix
     * @param string $chunkNameSuffix
     * @param string $defaultName
     * @return string
     */
    public function getTemplateName(
        \Twig_Environment $environment,
        $path, $chunkName,
        $chunkNamePrefix = '',
        $chunkNameSuffix = '',
        $defaultName = 'default'
    )
    {
        $templateName = sprintf($path . '%s%s%s.html.twig', $chunkNamePrefix, $chunkName, $chunkNameSuffix);
        if (!$environment->getLoader()->exists($templateName)) {
            $templateName = $path . $defaultName . '.html.twig';
        }
        return $templateName;
    }

}
