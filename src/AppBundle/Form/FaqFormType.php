<?php

namespace AppBundle\Form;

use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class FaqFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface $formBuilder
     * @param array                $options
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function buildForm(FormBuilderInterface $formBuilder, array $options)
    {
        $formBuilder
            ->add('faqCategory', TextType::class, [
                'label' => 'FaqCategory',
                'attr' => [
                    'readonly' => true,
                ],
            ])
            ->add('wordCode', TextType::class, [
                'label' => 'FaqWordCode',
            ])
            ->add('question', CKEditorType::class, [
                'label' => 'FaqQuestion',
            ])
            ->add('answer', CKEditorType::class, [
                'label' => 'FaqAnswer',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'FaqActive',
                'required' => false,
            ])
        ;
        $formBuilder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $faq = $event->getData();
            $form = $event->getForm();
            if (empty($faq->wordCode)) {
                $form->add('FaqCreate', SubmitType::class, [
                    'attr' => [
                        'class' => 'btn-primary',
                    ],
                ]);
            } else {
                $form->add('FaqUpdate', SubmitType::class, [
                    'attr' => [
                        'class' => 'btn-primary',
                    ],
                ]);
            }
        });
    }
}
