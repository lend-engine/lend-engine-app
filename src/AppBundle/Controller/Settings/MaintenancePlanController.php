<?php

namespace AppBundle\Controller\Settings;

use AppBundle\Entity\MaintenancePlan;
use AppBundle\Form\Type\Settings\MaintenancePlanType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenancePlanController extends Controller
{

    /**
     * @Route("admin/maintenance-plan/{id}", defaults={"id" = 0}, requirements={"id": "\d+"}, name="maintenance_plan")
     */
    public function maintenancePlanAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        if ($id) {
            $maintenancePlan = $this->getDoctrine()
                ->getRepository('AppBundle:MaintenancePlan')
                ->find($id);
            if (!$maintenancePlan) {
                throw $this->createNotFoundException(
                    'No maintenance type for id '.$id
                );
            }
        } else {
            $maintenancePlan = new MaintenancePlan();
        }

        $formOptions = [
            'em'     => $em,
            'action' => $this->generateUrl('maintenance_plan', array('id' => $id))
        ];
        $form = $this->createForm(MaintenancePlanType::class, $maintenancePlan, $formOptions);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($maintenancePlan->getInterval()) {
                $maintenancePlan->setAfterEachLoan(false);
            }

            $em->persist($maintenancePlan);
            $em->flush();
            $this->addFlash('success', 'Saved.');
            return $this->redirectToRoute('maintenance_plans');

        }

        return $this->render(
            'modals/settings/maintenance_plan.html.twig',
            array(
                'title' => 'Maintenance type',
                'subTitle' => '',
                'form' => $form->createView()
            )
        );

    }

}