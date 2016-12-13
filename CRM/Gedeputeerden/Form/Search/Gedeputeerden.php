<?php

class CRM_Gedeputeerden_Form_Search_Gedeputeerden extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  protected $_formValues;

  function buildForm(&$form) {
    $elements = array();

    // set page title
    CRM_Utils_System::setTitle(ts('Provinciale Statenleden en Gedeputeerden waar we in het bestuur zitten'));

    // add province filter
    $province = array('' => '- alle SP provincies -') + $this->_getSPProvinces();
    $form->addElement('select', 'provincie', 'SP provincie', $province);
    $elements[] = 'provincie';

    // add some checkboxes for specific relationships
    $relationships = $this->_getDeputyRelations();
    foreach ($relationships as $relationship_type_id => $description) {
      $form->addElement('checkbox', "relationship_type_id_{$relationship_type_id}", $description);
      $elements[] = "relationship_type_id_{$relationship_type_id}";
    }

    $form->assign('elements', $elements);
  }

  function &columns() {
    // link between column header and database field
    $columns = array(
      'Naam' => 'display_name',
      'Functie' => 'label_a_b',
      'Provincie' => 'nick_name',
    );
    return $columns;
  }

  function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    $sql = $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, NULL);
    //die($sql);
    return $sql;
  }

  function select() {
    // select database fields
    return "
      contact_a.id contact_id
      , contact_a.display_name
      , rt.`label_a_b`
      , prov.`nick_name`
    ";
  }

  function from() {
    return "
      FROM
        civicrm_contact contact_a
      INNER JOIN
        civicrm_relationship r ON r.`contact_id_a` = contact_a.id
      INNER JOIN
        civicrm_contact prov ON r.`contact_id_b` = prov.`id`
      INNER JOIN
        civicrm_entity_tag et ON et.entity_id = prov.id and et.entity_table = 'civicrm_contact'	 
      INNER JOIN
        `civicrm_relationship_type` rt ON rt.id = r.`relationship_type_id`
	  ";
  }

  function where($includeContactIDs = FALSE) {
    // let's check the possible relationships againts the selected relationships
    $relationshipsIDs = array();
    $selectedIDs = array();
    foreach ($this->_getDeputyRelations() as $id => $v) {
      // see if this one is selected
      if (array_key_exists("relationship_type_id_{$id}", $this->_formValues)) {
        $selectedIDs[] = $id;
      }

      // store the id anyway, we might need it if nothing is selected
      $relationshipsIDs[] = $id;
    }

    $where = "r.is_active = 1 and rt.id in (";
    if (count($selectedIDs) > 0) {
      // take the selected id's
      $where .= implode(', ', $selectedIDs);
    }
    else {
      // no relationship selected, take all of them
      $where .= implode(', ', $relationshipsIDs);
    }
    $where .= ") ";

    // see if a province was chosen
    $params = array();
    $province = CRM_Utils_Array::value('provincie', $this->_formValues);
    if ($province) {
      $params[1] = array($province, 'Integer');
      $where .= " AND prov.id = %1";
    }

    return $this->whereClause($where, $params);
  }

  private  function _getSPProvinces() {
    $provinces = array();

    // get a list of all SP departments (= contact sub type)
    $sql = "
      SELECT 
        c.id
        , c.`nick_name` provname
      FROM
        civicrm_contact c
      WHERE
        c.contact_sub_type LIKE '%SP_Provincie%'
        and c.nick_name is not null
      ORDER BY
        c.sort_name    
    ";

    $dao = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);
    while ($dao->fetch()) {
      $provinces = $provinces + array($dao->id => $dao->provname);
    }

    return $provinces;
  }

  private function _getDeputyRelations() {
    $relationships = array();

    // get a list of some specific relationship types
    $sql = "
      SELECT
        r.id as relationship_type_id
        , replace(r.label_a_b, ' prov', '') as description
      FROM
        civicrm_relationship_type r
      WHERE
        r.label_a_b IN ('Statenlid', '', 'Fractievoorzitter prov', 'Gedeputeerde')    
    ";

    $dao = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);
    while ($dao->fetch()) {
      $relationships[$dao->relationship_type_id] = $dao->description;
    }

    return $relationships;
  }
}