<?php
$words = new MOD_words();
$thumbPathMember = MOD_layoutbits::PIC_50_50($this->_session->get('Username'), '',$style='float_left framed');


$_newMessagesNumber = $this->model->getNewMessagesNumber($this->_session->get('IdMember'));

if ($_newMessagesNumber > 0) {
    $_mainPageNewMessagesMessage = $words->getFormatted('MainPageNewMessages', $_newMessagesNumber);
}

$LayoutBits = new MOD_layoutbits();
$ShowDonateBar = $LayoutBits->getParams('ToggleDonateBar');

$notify_widget = new NotifyMemberWidget;
$notify_widget->model = new NotifyModel;
$notify_widget->items_per_page = 4;

$inbox_widget = new MailboxWidget_Personalstart;
$inbox_widget->model = new MessagesModel;
$inbox_widget->items_per_page = 4;

$Trip = new TripsModel();
$TripcallbackId = $Trip->createProcess();
$editing = false;

$Places = new Places;
$Countries = $Places->getAllCountries();

?>