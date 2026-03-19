<?php

namespace App\Form;

use App\Entity\Stock;
use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => 'name',
                'label' => 'Product',
                'placeholder' => 'Select product',
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Stock In' => 'Added',
                    'Stock Out (Sold)' => 'Sold',
                    'Returned' => 'Returned',
                ],
                'label' => 'Transaction Type',
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantity',
            ])
            ->add('date', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Stock Movement Date',
                'required' => true,
            ])
            ->add('remarks', TextareaType::class, [
                'label' => 'Remarks (optional)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Stock::class,
        ]);
    }
}
