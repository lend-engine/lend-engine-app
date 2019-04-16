<?php

// src/AppBundle/Menu/Builder.php
namespace AppBundle\Menu;

use AppBundle\Entity\Loan;
use Knp\Menu\FactoryInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

class MenuBuilder
{

    private $menu;

    private $container;

    /**
     * @param FactoryInterface $factory
     * @param Container $container
     *
     * Add any other dependency you need
     */
    public function __construct(FactoryInterface $factory, Container $container)
    {
        $this->factory   = $factory;
        $this->container = $container;
    }

    /**
     * @return \Knp\Menu\ItemInterface
     */
    public function adminMenu()
    {

        $this->menu = $this->factory->createItem('root', array(
            'childrenAttributes' => array(
                'class' => 'sidebar-menu'
            )
        ));

        $this->addMenuItem('Dashboard', 'homepage', 'fa-bar-chart');
        $this->addMenuItem('Member site', 'home', 'fa-window-maximize');

        $this->addMenuItem('Loans', 'loan_list', 'fa-shopping-bag');
        $this->addChildItem('Loans', 'All', 'loan_list', '', '', ['status' => 'ALL']);
        $this->addChildItem('Loans', 'Pending', 'loan_list', '', '', ['status' => 'PENDING']);
        $this->addChildItem('Loans', 'On loan', 'loan_list', '', '', ['status' => 'ACTIVE']);
        $this->addChildItem('Loans', 'Overdue', 'loan_list', '', '', ['status' => 'OVERDUE']);
        $this->addChildItem('Loans', 'Reservations', 'loan_list', '', '', ['status' => Loan::STATUS_RESERVED]);

        $this->addMenuItem('Items', 'null', 'fa-cubes');
        $this->addChildItem('Items', 'Browse items', 'item_list', '');

        if ( $this->container->get('security.authorization_checker')->isGranted("ROLE_SUPER_USER") ) {
            $this->addChildItem('Items', 'Bulk update <sup>beta</sup>', 'import_items', '');
        }
        $this->addChildItem('Items', 'Add item', 'item_type', '');

        if ($this->container->get('settings')->getSettingValue('enable_waiting_list')) {
            $this->addChildItem('Items', 'Waiting list', 'item_waiting_list', '');
        }

        $this->addMenuItem('Contacts / Members', 'contact_list', 'fa-group');

        $this->addMenuItem('Reports', 'null', 'fa-signal');
        $this->addChildItem('Reports', 'Loans', 'report_loans', '');
        $this->addChildItem('Reports', 'Loaned items', 'report_items', '');
        $this->addChildItem('Reports', 'Non-loaned items', 'non_loaned_items', '');
        $this->addChildItem('Reports', 'Payments', 'report_payments', '');
        $this->addChildItem('Reports', 'Item costs', 'report_costs', '');
        $this->addChildItem('Reports', 'Memberships', 'membership_list', '');

        if ($this->container->get('tenant_information')->getIndustry() == "toys") {
            $this->addChildItem('Reports', 'Children', 'report_children', '');
        }

        if ( $this->container->get('security.authorization_checker')->isGranted("ROLE_SUPER_USER") ) {
            $this->addMenuItem('Settings', 'settings', 'fa-cogs');
        }

        return $this->menu;
    }

    /**
     * @return \Knp\Menu\ItemInterface
     */
    public function settingsMenu()
    {
        $this->menu = $this->factory->createItem('root', array(
            'childrenAttributes' => array(
                'class' => 'nav settings-nav'
            )
        ));

        $this->menu->addChild('General settings', array('route' => 'settings'));
        $this->menu->addChild('Billing', array('route' => 'billing'));
        $this->menu->addChild('Sites', array('route' => 'site_list'));
        $this->menu->addChild('Locations', array('route' => 'location_list'));

        $this->menu->addChild('Loans & Reservations', array('route' => 'settings_reservations'));
        if ($this->container->get('tenant_information')->getFeature('CustomEmail')) {
            $this->menu->addChild('Email templates', array('route' => 'settings_templates'));
        }
        $this->menu->addChild('Staff / team', array('route' => 'users_list'));

        $this->menu->addChild('Item categories', array('route' => 'tags_list'));
        $this->menu->addChild('Item fields', array('route' => 'product_field_list'));
        $this->menu->addChild('Item conditions', array('route' => 'itemCondition_list'));

        $this->menu->addChild('Check in prompts', array('route' => 'checkInPrompt_list'));
        $this->menu->addChild('Check out prompts', array('route' => 'checkOutPrompt_list'));

        $this->menu->addChild('Import contacts', array('route' => 'import_contacts'));
        $this->menu->addChild('Custom pages & links', array('route' => 'page_list'));

        $this->menu->addChild('Contact fields', array('route' => 'contact_field_list'));
        $this->menu->addChild('Membership types', array('route' => 'membershipType_list'));
        $this->menu->addChild('Payment methods', array('route' => 'payment_method_list'));

        return $this->menu;
    }

    /**
     * @return \Knp\Menu\ItemInterface
     */
    public function itemsMenuStacked()
    {
        return $this->itemsMenu(true);
    }

    /**
     * @param bool $stacked
     * @return \Knp\Menu\ItemInterface
     * @throws \Exception
     */
    public function itemsMenu($stacked = false)
    {

        if ($stacked == true) {
            $class = 'nav nav-pills nav-stacked items-nav';
        } else {
            $class = 'nav nav-pills items-nav';
        }

        $requestStack = $this->container->get('request_stack');
        $request = $requestStack->getCurrentRequest();

        $this->menu = $this->factory->createItem('root', array(
            'childrenAttributes' => array(
                'class' => $class
            )
        ));

        if ($this->container->get('settings')->getSettingValue('site_is_private')
            && !$this->container->get('security.authorization_checker')->isGranted('ROLE_USER')) {

            $txt = $this->container->get('translator')->trans("public_misc.log_in_first", [], 'member_site');
            $txt = "";
            $this->menu->addChild($txt, array(
                'route' => '',
                'extras' => array('safe_label' => true)
            ));

            return $this->menu;
        }

        // Show all items
        $parameters = [
            'show' => 'all',
            'locationId' => $request->get('locationId'),
            'e' => $request->get('e') // embed
        ];
        if ($request->get('show') == 'all') {
            $class = 'active';
        } else {
            $class = '';
        }
        $this->menu->addChild($this->container->get('translator')->trans("public_misc.link_all_items", [], 'member_site'), array(
            'route' => 'public_products',
            'routeParameters' => $parameters,
            'extras' => array('safe_label' => true)
        ))->setAttribute('class', $class);

        // Show recently added items
        $parameters = [
            'show' => 'recent',
            'locationId' => $request->get('locationId'),
            'e' => $request->get('e') // embed
        ];
        if ($request->get('show') == 'recent') {
            $class = 'active';
        } else {
            $class = '';
        }
        $this->menu->addChild($this->container->get('translator')->trans("public_misc.link_recent_items", [], 'member_site'), array(
            'route' => 'public_products',
            'routeParameters' => $parameters,
            'extras' => array('safe_label' => true)
        ))->setAttribute('class', $class);

        // Product pages by tag --------------

        /** @var $repo \AppBundle\Repository\ProductTagRepository */
        $repo = $this->container->get('doctrine')->getRepository('AppBundle:ProductTag');
        $tags = $repo->findAllOrderedBySort();

        foreach ($tags AS $tag) {

            /** @var $tag \AppBundle\Entity\ProductTag */

            if ($tag->getShowOnWebsite() != true) {
                continue;
            }

            $parameters = [
                'tagId' => $tag->getId(),
                'locationId' => $request->get('locationId'),
                'e' => $request->get('e') // embed
            ];

            if ($tag->getId() == $request->get('tagId')) {
                $class = 'active';
            } else {
                $class = '';
            }

            $this->menu->addChild($tag->getName(), array(
                'route' => 'public_products',
                'routeParameters' => $parameters,
                'class' => $class,
                'label' => $tag->getName(),
                'extras' => array('safe_label' => true)
            ))->setAttribute('class', $class);
        }

        return $this->menu;
    }

    /**
     * @return \Knp\Menu\ItemInterface
     */
    public function pagesMenuStacked()
    {
        return $this->pagesMenu(true);
    }

    /**
     * @param bool $stacked
     * @return \Knp\Menu\ItemInterface
     * @throws \Exception
     */
    public function pagesMenu($stacked = false)
    {

        $requestStack = $this->container->get('request_stack');
        $request = $requestStack->getCurrentRequest();

        if ($stacked == true) {
            $class = 'nav nav-pills nav-stacked custom-nav';
        } else {
            $class = 'nav nav-pills custom-nav';
        }

        $this->menu = $this->factory->createItem('root', array(
            'childrenAttributes' => array(
                'class' => $class
            )
        ));

        /** @var $repo \AppBundle\Repository\PageRepository */
        $repo = $this->container->get('doctrine')->getRepository('AppBundle:Page');
        $pages = $repo->findOrderedBySort();

        foreach ($pages AS $page) {

            /** @var $page \AppBundle\Entity\Page */

            if ($page->getVisibility() == "HIDDEN") {
                continue;
            }

            $icon = '';

            if ($this->container->get('security.authorization_checker')->isGranted("ROLE_ADMIN")) {
                // Show all non-hidden pages

            } else if ($this->container->get('security.authorization_checker')->isGranted("ROLE_USER")) {
                // Show member-only pages and public pages
                if ($page->getVisibility() == "ADMIN") {
                    continue;
                }
            } else {
                // Show public only pages
                if ($page->getVisibility() != "PUBLIC") {
                    continue;
                }
            }

            $parameters = [
                'pageId' => $page->getId()
            ];

            if ($page->getId() == $request->get('pageId')) {
                $class = 'active';
            } else {
                $class = '';
            }

            if ($url = $page->getUrl()) {
                if (strstr($url, 'http')) {
                    $target = '_blank';
                } else {
                    $target = '_top';
                }
                $this->menu->addChild($page->getName(), array(
                    'uri' => $url,
                    'class' => $class,
                    'label' => $page->getName().$icon,
                    'extras' => array('safe_label' => true)
                ))->setAttribute('class', $class)->setLinkAttributes(array('target' => $target));
            } else {
                $this->menu->addChild($page->getName(), array(
                    'route' => 'public_page',
                    'routeParameters' => $parameters,
                    'class' => $class,
                    'label' => $page->getName().$icon,
                    'extras' => array('safe_label' => true)
                ))->setAttribute('class', $class);
            }


        }

        return $this->menu;
    }

    /**
     * @return \Knp\Menu\ItemInterface
     */
    public function accountMenu()
    {
        $this->menu = $this->factory->createItem('root', array(
            'childrenAttributes' => array(
                'class' => 'nav nav-pills'
            )
        ));

        if ( $this->container->get('security.authorization_checker')->isGranted("ROLE_ADMIN") ) {

        } else {
            $profileLabel = $this->container->get('translator')->trans("public_user_menu.contact_info", [], 'member_site');
            $this->menu->addChild($profileLabel, array('route' => 'fos_user_profile_show'));
        }

        $loanText = $this->container->get('translator')->trans("Loans", [], 'member_site');
        $loanLabel = $loanText;

        $this->menu->addChild($loanLabel, array('route' => 'loans'));
        $this->menu->addChild($this->container->get('translator')->trans("Payments", [], 'member_site'), array('route' => 'payments'));

        return $this->menu;
    }

    /**
     * @param $label
     * @param $route
     * @param string $icon
     */
    private function addMenuItem($label, $route, $icon = '')
    {
        // Add menu tags:
        // <small class="label pull-right bg-green">new</small>
        $this->menu->addChild($label, array(
            'route' => $route,
            'class' => 'treeview',
            'childrenAttributes' => array('class' => 'treeview-menu',),
            'label' => '<i class="fa '.$icon.'"></i> <span> '.$label.'</span>',
            'extras' => array('safe_label' => true)
        ));
    }

    /**
     * @param $parent
     * @param $label
     * @param $route
     * @param string $icon
     * @param string $tag
     * @param array $routeParameters
     */
    private function addChildItem($parent, $label, $route, $icon = '', $tag = '', $routeParameters = array())
    {
        $this->menu[$parent]->addChild('<i class="fa '.$icon.'"></i> <span>'.$label.'</span>'.$tag,
            array(
                'route' => $route,
                'routeParameters' => $routeParameters,
                'extras' => array('safe_label' => true),
            ));
    }

}