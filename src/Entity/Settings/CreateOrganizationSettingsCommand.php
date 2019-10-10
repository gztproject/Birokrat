<?php

namespace App\Entity\Settings;

class CreateOrganizationSettingsCommand
{
    public $invoicePrefix;
    public $defaultPaymentDueIn;
    public $referenceModel;
    public $IssueInvoiceDebit;
    public $IssueInvoiceCredit;
    public $InvoicePaidDebit;
    public $InvoicePaidCredit;
    public $IncurredTravelExpenseDebit;
    public $IncurredTravelExpenseCredit;
    public $PaidTravelExpenseDebit;
    public $PaidTravelExpenseCredit;
    public $ReceivedIncomingInvoiceCredit;
    public $ReceivedIncomingInvoiceDebit;
    public $PaidIncomingInvoiceCredit;
    public $PaidIncomingInvoiceDebit;
    public $RefundedIncomingInvoiceCredit;
    public $RefundedIncomingInvoiceDebit;
    public $RejectedIncomingInvoiceCredit;
    public $RejectedIncomingInvoiceDebit;
    
    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {
    	$this->$name = $value;
    } 
}
