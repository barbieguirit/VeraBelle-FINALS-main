<?php

namespace App\Form;

use App\Entity\Order;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customerName', TextType::class, [
                'label' => 'Customer Name',
                'attr' => ['placeholder' => 'Enter customer name'],
            ])
            ->add('customerEmail', EmailType::class, [
                'label' => 'Customer Email',
                'required' => false,
                'attr' => ['placeholder' => 'Optional email address'],
            ])
            ->add('customerPhone', TextType::class, [
                'label' => 'Customer Phone',
                'required' => false,
                'attr' => ['placeholder' => 'Optional contact number'],
            ])
            ->add('shippingAddress', TextareaType::class, [
                'label' => 'Shipping Address',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Enter full address'],
            ])
            ->add('totalAmount', NumberType::class, [
                'label' => 'Total Amount (₱)',
                'scale' => 2,
                'attr' => ['placeholder' => '0.00'],
            ])
            ->add('paymentStatus', ChoiceType::class, [
                'label' => 'Payment Status',
                'choices' => [
                    'Pending' => 'pending',
                    'Paid' => 'paid',
                    'Failed' => 'failed',
                    'Refunded' => 'refunded',
                ],
            ])
            ->add('orderStatus', ChoiceType::class, [
                'label' => 'Order Status',
                'choices' => [
                    'New' => 'new',
                    'Processing' => 'processing',
                    'Shipped' => 'shipped',
                    'Completed' => 'completed',
                    'Cancelled' => 'cancelled',
                ],
            ])
            ->add('orderDate', DateTimeType::class, [
                'label' => 'Order Date',
                'widget' => 'single_text',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
