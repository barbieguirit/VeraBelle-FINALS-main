<?php

namespace App\Form;

use App\Entity\Customer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Basic Information
            ->add('fullName', TextType::class, [
                'label' => 'Full Name',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Phone Number',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Customer Status',
                'choices' => [
                    'Active' => 'Active',
                    'Inactive' => 'Inactive',
                    'Suspended' => 'Suspended',
                ],
            ])
            ->add('registeredDate', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Registered Date',
            ])
            
            // Address Information
            ->add('shippingAddress', TextareaType::class, [
                'label' => 'Shipping Address',
                'required' => false,
            ])
            ->add('billingAddress', TextareaType::class, [
                'label' => 'Billing Address',
                'required' => false,
            ])
            
            // Notes
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Customer::class,
        ]);
    }
}