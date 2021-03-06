<?php

namespace AppBundle\Controller\MemberSite;

use AppBundle\Entity\Loan;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AddConsumablesController
 * @package AppBundle\Controller\MemberSite
 */
class AddConsumablesController extends Controller
{
    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @Route("loan/{loanId}/add-stock-item", name="add_stock_item", requirements={"loanId": "\d+"})
     */
    public function addStockItem($loanId)
    {
        /** @var \AppBundle\Entity\Loan $loan */
        $loan = $this->get('service.loan')->get($loanId);
        $this->get('session')->set('active-loan', $loanId);

        $type = '';
        switch ($loan->getStatus()) {
            case Loan::STATUS_RESERVED:
                $type = "reservation";
                break;
            case Loan::STATUS_PENDING:
                $type = "loan";
                break;
        }

        $this->get('session')->set('active-loan-type', $type);
        $this->addFlash('success', "Choose stock item to add to {$type} {$loanId}.");

        return $this->redirectToRoute('public_products', ['type' => 'stock']);
    }
}
