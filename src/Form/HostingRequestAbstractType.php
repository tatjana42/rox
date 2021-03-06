<?php

namespace App\Form;

use App\Entity\Message;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HostingRequestAbstractType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => Message::class,
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'app_message';
    }

    protected function addMessageTextArea(FormInterface $form, $placeholder)
    {
        $form
            ->add('message', CkEditorType::class, [
                'invalid_message' => 'request.message.empty',
                'placeholder' => $placeholder,
            ])
        ;
    }
}
