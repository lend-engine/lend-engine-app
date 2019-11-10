<?php

namespace AppBundle\Controller\Admin\Item\Tabs;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityRepository;

class ItemFilesController extends Controller
{

    /**
     * @Route("admin/item/{id}/files", name="item_files", defaults={"id" = 0}, requirements={"id": "\d+"})
     */
    public function itemFilesAction(Request $request, $id)
    {

        $em = $this->getDoctrine()->getManager();

        /** @var \AppBundle\Repository\InventoryItemRepository $itemRepo */
        $itemRepo = $em->getRepository('AppBundle:InventoryItem');

        $item = $itemRepo->find($id);

        return $this->render(
            'admin/item/tabs/item_files.html.twig',
            array(
                'product' => $item,
            )
        );

    }

}