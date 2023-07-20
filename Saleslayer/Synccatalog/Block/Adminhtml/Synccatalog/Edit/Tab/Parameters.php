<?php
namespace Saleslayer\Synccatalog\Block\Adminhtml\Synccatalog\Edit\Tab;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

// use \Magento\Catalog\Model\Category as categoryModel;

/**
 * Synccatalog page edit form Parameters tab
 */
class Parameters extends \Magento\Backend\Block\Widget\Form\Generic implements \Magento\Backend\Block\Widget\Tab\TabInterface
{
    /**
     * @var \Magento\Store\Model\System\Store
     */
    protected $_systemStore;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param \Magento\Store\Model\System\Store $systemStore
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Store\Model\System\Store $systemStore,
        TimezoneInterface $timezone,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
        $this->_systemStore = $systemStore;
        $this->timezone = $timezone;
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

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('General Parameters')]);

        if ($model->getId()) {
            $fieldset->addField('id', 'hidden', ['name' => 'id']);
        }

        $modelData = $model->getData();

        if (empty($modelData)){
            $modelData['store_view_ids'] = ['0'] ;
        }else{
            $modelData['store_view_ids'] = json_decode($modelData['store_view_ids'],1);
        }

        $auto_sync_options = [];
        $auto_sync_values = [0, 1, 3, 6, 8, 12, 15, 24, 48, 72];

        foreach ($auto_sync_values as $auto_sync_value) {
            if ($auto_sync_value == 0){
                array_push($auto_sync_options, ['label' => ' ', 'value' => $auto_sync_value]);
            }else{
                array_push($auto_sync_options, ['label' => $auto_sync_value.'H', 'value' => $auto_sync_value]);
            }
        }

        $datetime_last_sync = '';

        if(!empty($modelData['last_sync'])){
            $last_sync_timezoned = $this->timezone->date($modelData['last_sync'])->format('M d, Y, H:i:s A');
            $time_lapsed = '<br><small>Since last sync of this connector step: '.$this->elapsed_time(strtotime($last_sync_timezoned)).'</small>';
        }else{
            $time_lapsed = '';
        }

        $fieldset->addField(
            'auto_sync',
            'select',
            [
                'name' => 'auto_sync',
                'label' => __('Auto Synchronization Every'),
                'title' => __('Auto Synchronization Every'),
                'id' => 'auto_sync',
                'required' => false,
                'values' => $auto_sync_options,
                'disabled' => false,
                'after_element_html' =>$time_lapsed,
                'class' => 'conn_field'
            ]
        );
        
        if (!empty($modelData) && isset($modelData['auto_sync']) && $modelData['auto_sync'] >= 24){
            $hour_input_disabled = false;
        }else{
            $hour_input_disabled = true;
        }

        $auto_sync_hour_options = [];
        $hours_range = range(0, 23);
        foreach ($hours_range as $hour){
            $auto_sync_hour_options[$hour] = [
                'label' => (strlen($hour) == 1 ? '0'.$hour : $hour).':00',
                'value' => $hour
            ];
        }

        $fieldset->addField(
            'auto_sync_hour',
            'select',
            [
                'name' => 'auto_sync_hour',
                'label' => __('Preferred auto-sync hour'),
                'title' => __('Preferred auto-sync hour'),
                'id' => 'auto_sync_hour',
                'required' => false,
                'values' => $auto_sync_hour_options,
                'disabled' =>  $hour_input_disabled,
                'after_element_html' =>'<br><small id="servertime">Current server time: '.date('H:i').'</small>',
                'class' => 'conn_field'
            ]
        );

        if (!$this->_storeManager->isSingleStoreMode()) {
            $fieldset->addField(
                'store_view_ids',
                'multiselect',
                [
                    'name' => 'store_view_ids[]',
                    'label' => __('Store View'),
                    'title' => __('Store View'),
                    'required' => false,
                    'values' => $this->_systemStore->getStoreValuesForForm(false, true),
                    'disabled' => false,
                    'after_element_html' => "<br><small>If only 'All Store Views' is selected, the information will by synchronized at all store views, otherwise only in the selected ones.</small>",
                    'class' => 'conn_field'
                ]
            );

        } else {
            $fieldset->addField(
                'store_view_ids',
                'hidden',
                ['name' => 'store_view_ids[]', 'value' => $this->_storeManager->getStore(true)->getId()]
            );
            $model->setStoreIds($this->_storeManager->getStore(true)->getId());
        }

        $this->_eventManager->dispatch('adminhtml_synccatalog_edit_tab_parameters_prepare_form', ['form' => $form]);

        $form->setValues($modelData);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    private function elapsed_time($timestamp, $precision = 2) {

        $time = time() - $timestamp;
        $result = '';

        $a = [
            'decade' => 315576000,
            'year' => 31557600,
            'month' => 2629800,
            'week' => 604800,
            'day' => 86400,
            'hour' => 3600,
            'min' => 60,
            'sec' => 1
        ];

        $i = 0;
        foreach($a as $k => $v) {
            $$k = floor($time/$v);
            if ($$k) $i++;
            $time = $i >= $precision ? 0 : $time - $$k * $v;
            $s = $$k > 1 ? 's' : '';
            $$k = $$k ? $$k.' '.$k.$s.' ' : '';
            $result .= $$k;
        }

        return $result ? $result.'ago' : '1 sec to go';
    }

    /**
     * Prepare label for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return __('General parameters');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return __('General parameters');
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
