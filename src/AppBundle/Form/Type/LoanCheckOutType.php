<?php
//src/AppBundle/Form/Type/LoanCheckOutType.php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoanCheckOutType extends AbstractType
{
    protected $em;
    protected $paymentDue;
    protected $user;
    protected $stripePaymentMethodId;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $this->em = $options['em'];
        $this->paymentDue = $options['paymentDue'];
        $this->user = $options['user'];

        $paymentMethodRepo = $this->em->getRepository("AppBundle:PaymentMethod");
        $activePaymentMethods = $paymentMethodRepo->findAllOrderedByName();

        $builder->add('paymentMethod', EntityType::class, array(
            'label' => 'Payment method',
            'class' => 'AppBundle:PaymentMethod',
            'choice_label' => 'name',
            'choices' => $activePaymentMethods,
            'required' => false,
            'mapped' => true,
            'attr' => [
                'class' => 'payment-method'
            ]
        ));

        $builder->add('paymentAmount', CurrencyamountType::class, array(
            'label' => 'Payment taken now',
            'data' => number_format($this->paymentDue, 2),
            'required' => false,
            'attr' => [
                'class' => 'payment-amount'
            ]
        ));

        $builder->add('paymentNote', TextareaType::class, array(
            'label' => 'Payment note',
            'required' => false,
            'attr' => array(
                'rows' => 2
            )
        ));

        // Set by JS on the return from Stripe.com checkout
        $builder->add('stripeToken', HiddenType::class, array(
            'label' => 'stripeToken',
            'required' => false,
            'mapped' => false,
            'attr' => [
                'class' => 'stripe-token'
            ]
        ));

        $builder->add('stripeCardId', HiddenType::class, array(
            'label' => 'stripeCardId',
            'required' => false,
            'mapped' => false,
            'attr' => [
                'class' => 'stripe-card-id'
            ]
        ));

    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'em' => null,
            'user' => null,
            'stripePaymentMethodId' => null,
            'paymentDue' => null
        ));
    }

}