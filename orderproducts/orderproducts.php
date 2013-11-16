<?php
/*
* The MIT License (MIT)
* 
* Copyright (c) 2013 Iztok Svetik
* 
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
* 
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
* 
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*
* -----------------------------------------------------------------------------
* @author   Iztok Svetik
* @website  http://www.isd.si
* @github   https://github.com/iztoksvetik
*/


if (!defined('_PS_VERSION_'))
  exit;
 
class OrderProducts extends Module
{
  public function __construct()
  {
    $this->name = 'orderproducts';
    $this->tab = 'administration';
    $this->version = '1.1';
    $this->author = 'Iztok Svetik - isd.si';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.5.9'); 
 
    parent::__construct();
 
    $this->displayName = $this->l('Order products');
    $this->description = $this->l('Orders products by sales.');
 
    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
 
  }
 
  public function install()
  {
    if (Shop::isFeatureActive())
      Shop::setContext(Shop::CONTEXT_ALL);
   
    return parent::install()
    && Configuration::updateValue('ORDERPRODUCTS_CATS', '')
    && Configuration::updateValue('ORDERPRODUCTS_VALUE', 0);

  }

  public function uninstall()
  {
    return parent::uninstall()
    && Configuration::deleteByName('ORDERPRODUCTS_CATS')
    && Configuration::deleteByName('ORDERPRODUCTS_VALUE');
  }

  public function getContent()
  {
    if (Tools::isSubmit('submitorderproducts')) { 
      $categories = '';
      if (Tools::isSubmit('categoryBox')) {
        $categories = Tools::getValue('categoryBox');
        $categories = json_encode($categories);
      }
      Configuration::updateValue('ORDERPRODUCTS_CATS', $categories);
      Configuration::updateValue('ORDERPRODUCTS_VALUE',
          Tools::getValue('ORDERPRODUCTS_VALUE'));
    }
    if (Tools::isSubmit('shuffleorderproducts')) { 
      $this->reorderProducts();
    }
    
    return $this->displayForm();
  }

  private function reorderProducts()
  {
    $categories = Category::getSimpleCategories($this->context->language->id);
    $json_cats = Configuration::get('ORDERPRODUCTS_CATS');
    $value = (int)Configuration::get('ORDERPRODUCTS_VALUE');
    $excluded_cats = array();
    if ($json_cats && $json_cats != '') {
      $excluded_cats = json_decode($json_cats);
    }

    foreach ($categories as $cat) {
      if (!in_array($cat['id_category'], $excluded_cats))
        {
        if ($value == 0) {
          $sql = "SELECT cp.*
                  FROM `"._DB_PREFIX_."category_product` cp 
                  LEFT JOIN `"._DB_PREFIX_."product_sale` ps 
                  ON (cp.`id_product` = ps.`id_product`)
                  WHERE cp.`id_category` = ". $cat['id_category'] .
                  " ORDER BY ps.`quantity` DESC, cp.`position` ASC";
        }
        else {
          $sql = "SELECT cp.*
                  FROM `"._DB_PREFIX_."category_product` cp 
                  LEFT JOIN `"._DB_PREFIX_."product_sale` ps 
                    ON (cp.`id_product` = ps.`id_product`)  
                  LEFT JOIN `"._DB_PREFIX_."product` p
                    ON (cp.`id_product` = p.`id_product`)
                  WHERE cp.`id_category` = ". $cat['id_category'] . "
                  ORDER BY ps.`quantity` * p.`price` DESC, cp.`position` ASC";
        }
        $result = Db::getInstance()->executeS($sql);
        $i=0;
        foreach ($result as $r) {
          $sql = "UPDATE `"._DB_PREFIX_."category_product` 
                   SET `position` = ". $i . "
                   WHERE `id_category` = " . $r['id_category'] . " AND `id_product` = " . $r['id_product'];
          Db::getInstance()->execute($sql);
          $i++;
        }
      }
    }
  }

  public function displayForm()
  {
    // Get default Language
    $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
    $json_cats = Configuration::get('ORDERPRODUCTS_CATS');
    $categories = array();
    if ($json_cats && $json_cats != '') {
      $categories = json_decode($json_cats);
    }
    $helper = new HelperForm();
    // Init Fields form array
    $fields_form[0]['form'] = array(
        'legend' => array(
            'title' => $this->l('Ordering settings'),
            'image' => '../modules/orderproducts/logo.gif'
        ),
        'input' => array(
            array(
                'type' => 'radio',
                'label' => $this->l('Order by'),
                'name' => 'ORDERPRODUCTS_VALUE',
                'desc' => $this->l('Allows you to order by value of sold products or quantity.'),
                'class' => 't',
                'values' => array(
                  array(
                    'id' => 'value_on',
                    'value' => 1,
                    'label' => $this->l('Value')
                  ),
                  array(
                    'id' => 'value_off',
                    'value' => 0,
                    'label' => $this->l('Quantity')
                  )
                )
            ),
            array(
              'type' => 'categories_select',
              'label' => $this->l('Choose categories to exclude'),
              'name' => 'categoryBox',
              'category_tree' => $helper->renderCategoryTree(
                     null,
                     $categories,
                     'categoryBox',
                     false,
                     true,
                     array(),
                     false,
                     false
              )
            )
        ),
        'submit' => array(
            'title' => $this->l('Save settings'),
            'class' => 'button'
        )
    );
     
    
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
     
    // Language
    $helper->default_form_language = $default_lang;
    $helper->allow_employee_form_lang = $default_lang;
     
    // Title and toolbar
    $helper->title = $this->displayName;
    $helper->show_toolbar = true;        // false -> remove toolbar
    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'submit'.$this->name;
    $helper->toolbar_btn = array(
        'save' =>
        array(
            'desc' => $this->l('Save'),
            'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
            '&token='.Tools::getAdminTokenLite('AdminModules'),
        ),
        'refresh-index' => array(
            'desc' => $this->l('Reorder products'),
            'href' => AdminController::$currentIndex.'&configure='.$this->name.'&shuffle'.$this->name.
            '&token='.Tools::getAdminTokenLite('AdminModules')
        ),
        'back' => array(
            'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Back to list')
        )
    );

    $helper->fields_value = array(
        'ORDERPRODUCTS_VALUE'  => Configuration::get('ORDERPRODUCTS_VALUE')
    );
     
     
    return $helper->generateForm($fields_form);
}

}
