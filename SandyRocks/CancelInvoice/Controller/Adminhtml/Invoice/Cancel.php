<?php


namespace SandyRocks\CancelInvoice\Controller\Adminhtml\Invoice;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Api\OrderManagementInterface;

class Cancel extends \Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction
{

    protected $orderManagement;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        OrderManagementInterface $orderManagement,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Sales\Model\Order\Invoice $invoiceModel,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        parent::__construct($context, $filter);
        $this->collectionFactory = $collectionFactory;
        $this->orderManagement = $orderManagement;
        $this->request = $request;
        $this->invoiceModel = $invoiceModel;
        $this->messageManager = $messageManager;
    }

    
    protected function massAction(AbstractCollection $collection)
    { 
        $invoice_not_canceled = array();
        $invoice_canceled = array();
        $itemsArray = $this->request->getPostValue('selected');
        foreach ($itemsArray as $key => $invoiceId) {
            $invoice = $this->invoiceModel->load($invoiceId);
            $order = $invoice->getOrder();
            if(!$invoice->isCanceled()){
                $invoice->cancel();
                $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_CANCELED);
                foreach ($invoice->getAllItems() as $item) {
                    $getQtyInvoiced = $item->getOrderItem()->getQtyInvoiced();
                    $invoiceQty = $item->getQty();
                    $item->getOrderItem()->setQtyInvoiced($getQtyInvoiced - $invoiceQty);
                    $item->save();        
                }
                $order->setTotalPaid($order->getTotalPaid() - $invoice->getGrandTotal());
                $invoice->save();
                $order->save();
                $invoice_canceled[] = $invoiceId;
            }
            else{
                $invoice_not_canceled[] = $invoiceId;
            }
        }
        if(!empty($invoice_not_canceled)){
            $this->messageManager->addError(__('Unable to Cancel Invoices/ Invoices #'.implode(',', $invoice_not_canceled)));
        }
        if(!empty($invoice_canceled)){
            $this->messageManager->addSuccess(__('Invoices Successfully canceled. Invoice Ids #'.implode(',', $invoice_canceled)));
        }
        return $this->resultRedirectFactory->create()->setPath('sales/invoice/');
    }
}