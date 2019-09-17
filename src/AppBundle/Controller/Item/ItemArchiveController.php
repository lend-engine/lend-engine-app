<?php

namespace AppBundle\Controller\Item;

use AppBundle\Entity\InventoryItem;
use AppBundle\Entity\InventoryLocation;
use AppBundle\Entity\ItemMovement;
use AppBundle\Entity\Payment;
use Doctrine\DBAL\DBALException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Form\Type\ItemMoveType;
use AppBundle\Form\Type\ItemRemoveType;
use Postmark\PostmarkClient;
use Postmark\Models\PostmarkException;

class ItemArchiveController extends Controller
{

    /**
     * Modal content for removing items
     * @Route("admin/item/{id}/archive/", name="item_archive")
     */
    public function itemArchiveAction(Request $request, $id)
    {
        /** @var \AppBundle\Services\InventoryService $inventoryService */
        $inventoryService = $this->get('service.inventory');

        $em = $this->getDoctrine()->getManager();

        $inventoryItemRepo = $em->getRepository('AppBundle:InventoryItem');

        /** @var \AppBundle\Entity\InventoryItem $inventoryItem */
        $inventoryItem = $inventoryItemRepo->find($id);

        $form = $this->createForm(ItemRemoveType::class, null, array(
            'action' => $this->generateUrl('item_archive', ['id' => $inventoryItem->getId()])
        ));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $userNote = $form->get('notes')->getData();

            if ( $inventoryService->itemRemove($inventoryItem, $userNote) ) {
                $this->addFlash('success', 'Item deleted.');
            } else {
                $this->addFlash('error', "Failed to delete item.");
            }

            return $this->redirectToRoute('item_list');

        }

        return $this->render(
            'shared/modals/item_archive.html.twig',
            array(
                'item' => $inventoryItem,
                'form' => $form->createView()
            )
        );

    }

    /**
     * Modal content for adding inventory to a loan
     * Contents are delivered via AJAX route "select_inventory"
     * @Route("admin/inventory/search/", name="inventory_search")
     */
    public function searchAction(Request $request)
    {
        return $this->render(
            'item/item_search.html.twig',
            [
                'loanId' => $request->get('loanId')
            ]
        );
    }
}