<?php
namespace Saleslayer\Synccatalog\Block\Adminhtml\Synccatalog\Edit\Tab;

// use \Magento\Catalog\Model\Category as categoryModel;

/**
 * Synccatalog page edit form main tab
 */
class Main extends \Magento\Backend\Block\Widget\Form\Generic implements \Magento\Backend\Block\Widget\Tab\TabInterface
{

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * Prepare form
     *
     * @return $this
     */
    protected function _prepareForm()
    {
        /* @var $model \Magento\Cms\Model\Page */
        $model = $this->_coreRegistry->registry('synccatalog');

        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create();

        $form->setHtmlIdPrefix('synccatalog_main_');

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Connector Credentials')]);

        if ($model->getId()) {
            $fieldset->addField('id', 'hidden', ['name' => 'id']);
        }

        $modelData = $model->getData();

        if (empty($modelData)){
            $isElementDisabled = false;
        }else{
            $isElementDisabled = true;

            $fieldset->addField(
                'ajax_url',
                'hidden',
                [
                    'name' => 'ajax_url',
                    'id' => 'ajax_url'
                ]
            );
            
            $modelData['ajax_url'] = $this->getUrl('synccatalog/ajax/updateconnfield');
        }

        $fieldset->addField(
            'connector_id',
            'text',
            [
                'name' => 'connector_id',
                'label' => __('Connector ID'),
                'title' => __('Connector ID'),
                'required' => true,
                'disabled' => $isElementDisabled
            ]
        );

        $fieldset->addField(
            'secret_key',
            'text',
            [
                'name' => 'secret_key',
                'label' => __('Secret Key'),
                'title' => __('Secret Key'),
                'required' => true,
                'disabled' => $isElementDisabled
            ]
        );

        $fieldset->addField(
            'ajax_url_time',
            'hidden',
            [
                'name' => 'ajax_url_time',
                'id' => 'ajax_url_time'
            ]
        );
        $modelData['ajax_url_time'] = $this->getUrl('synccatalog/ajax/showdebbug');

        $this->_eventManager->dispatch('adminhtml_synccatalog_edit_tab_main_prepare_form', ['form' => $form]);

        $form->setValues($modelData);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Prepare label for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return __('Credentials');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return __('Credentials');
    }

    /**
     * {@inheritdoc}
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden()
    {
        return false;
    }

}
