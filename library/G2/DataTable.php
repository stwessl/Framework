<?php

class G2_DataTable extends Mvc_Base {

	private $filter, $functions = [], $renderers = [];
	var $data, $type, $sql, $bindings, $limit = 10;
	private $sql_query;

	function add_query($type, $sql = '', $bindings = []) {
		$this->type = $type;
		$this->sql = $sql;
		$this->bindings = $bindings;
	}

	function add_exec_query($sql_query){
		$this->sql_query = $sql_query;
	}

	/**
	 * Set the data that needs to be imported manually
	 * @param type $data
	 */
	function set_data($data, $limit) {
		if (is_array($data)) {
			$this->data = $data;
			$this->limit = $limit;
		} else {
			throw new Exception('Array expected as argument $data');
		}
	}

	function set_page_limit($limit) {
		$this->limit = $limit;
	}

	function get_functions() {
		return $this->functions;
	}

	/**
	 * Add function column to generated table with functions replacing
	 * $$fieldname$$ with fields from the current record
	 *
	 * @param type $action
	 * @param type $label
	 * @param type $classes
	 */
	function add_function($action, $label, $classes = []) {
		$this->functions[] = ['action' => $action, 'label' => $label, 'classes' => $classes];
	}

	function add_renderer(G2_DataTable_Renderer $renderer){
		$this->renderers[] = $renderer;
	}
	function render_value($field,$value,$data){
		foreach($this->renderers as $render) {
			if($render->field() == $field){

				$value = $render->render($field,$value,$data);
			}
		}

		return $value;
	}

	/**
	 * Conditainal function add on rows
	 * [
	 * 		'field' => $field ,
	 * 		'condition' => '== / != / >='
	 * 		'value' => $value
	 * ]
	 *
	 * Multiple conditions are regarded as 'or'
	 *
	 * @param type $condition
	 * @param type $action
	 * @param type $label
	 * @param type $classes
	 */
	function add_conditional_function($conditions, $action, $label, $classes = []) {
		$this->functions[] = ['conditions' => $conditions, 'action' => $action, 'label' => $label, 'classes' => $classes];
	}

	function render_functions($fields) {

		$f_string = '';
		foreach ($this->functions as $function) {


			if (isset($function['conditions'])) {
//				var_dump(isset($function['conditions']));exit;
				// ck if this condition is correct
				$conditions = $function['conditions'];
				if (!$this->test_conditions($fields, $conditions)) {
					continue;
				}
			}
			$f_string .= $this->generate_function($function, $fields);
		}
		return $f_string;
	}

	private function generate_function($function, $fields) {
		$action = $function['action'];
		foreach ($fields as $key => $value) {
			$action = str_replace("[$key]", $value, $action);
		}
		$anchor = "<a href=\"$action\" class=\" btn " . implode(' ', $function['classes']) . "\">{$function['label']}</a>";
		return $anchor;
	}

	private function test_conditions($fields, $conditions) {

		if (!is_array(reset($conditions))) {
			$conditions = [$conditions];
		}

		$approved = false;
		$con_op = [
			'==', '>', '>=', '!=', '<', '<='
		];
		foreach ($conditions as $single) { // Or Condition by default
			// test that the conditional operator is allowed
			if (in_array($single['condition'], $con_op)) {
				$field = $single['field'];
				$cond = $single['condition'];
				$value = $single['value'];

				if (!is_array($fields) && get_class($fields) == 'RedBeanPHP\OODBBean') {
					$field_c = $fields->export();
				} else
					$field_c = $fields;

				eval("\$result = \$fields['$field'] $cond \$value;");

				if ($result) {
					$approved = true;
					break;
				}
			} else {
				continue;
			}
		}
		return $approved;
	}

	function render() {
		$twig_cache = ROOT_DIR . 'cache/tables/';
		$twig_folder = __DIR__ . '/DataTable';

		$params = array(
			'cache' => $twig_cache,
			'auto_reload' => true,
			'autoescape' => false,
//			'debug' => true
		);
		$loader = new Twig_Loader_Filesystem($twig_folder);
		$twig = new Twig_Environment($loader, $params);
//		var_dump($twig_folder);exit;
		$current_url = substr(Mvc_Functions::curPageURL(), 0, strpos(Mvc_Functions::curPageURL(), '?') !== false ? strpos(Mvc_Functions::curPageURL(), '?') : strlen(Mvc_Functions::curPageURL()));
		$copy = $_GET;
		unset($copy['p']);
		if (empty($copy)) {
			$get_con = '?';
		} else {
			$current_url .= '?' . Mvc_Functions::GET_to_string($copy);
			$get_con = '&';
		}

		return $twig->render('table.twig', [
					'data' => $this->get_resultset(),
					'pages' => Mvc_Db::get_last_total_pages() != false ? Mvc_Db::get_last_total_pages() : $this->total_pages,
					'current' => Mvc_Db::get_current_page() != false ? Mvc_Db::get_current_page() : $this->current_page,
					'headers' => $this->get_headers(),
					'functions' => $this->get_functions(),
					'instance' => $this,
					'get_con' => $get_con,
					'current_url' => $current_url
		]);
	}

	function get_resultset() {

		if(!empty($this->sql_query)){
			$data = Mvc_Db::paginate_query($this->sql_query, $this->limit);

		}

		if (!empty($this->data)) {
			if (isset($_GET['p']) && is_numeric($_GET['p'])) {
				$p = $_GET['p'];
			} else {
				$p = 1;
			}

			$data = array_slice($this->data, (($p - 1) * $this->limit), $this->limit);
			$this->current_page = $p;
			$this->total_pages = ceil(count($this->data) / $this->limit);
		}

		if (!empty($this->type)) {

			$data = Mvc_Db::paginate_findAll($this->type, $this->limit, $this->sql, $this->bindings);
		}


		return $data;
	}

	function get_count() {
		return Mvc_Db::get_last_total_pages();
	}

	function set_filter($filter) {
		$this->filter = $filter;
	}

	function get_headers() {

		if (isset($this->headers)) {
			return $this->headers;
		}
		if (isset($this->data)) {
			$first = clone reset($this->data);
		} else {
			$first = reset($this->get_resultset());

		}
		//Filter out all unneeded fields if set
		if (isset($this->filter) && !empty($this->filter)) {
			$first = reset($this->filter_out([$first], $this->filter));
		}
		if (!is_array($first) && $first) {
			return array_keys($first->export());
		} else if(is_array($first) && !empty ($first)){
			return array_keys($first);
		} else
			return [];
	}

	private function filter_out($objects, $keys) {

		foreach ($objects as $key => $object) {
			foreach ($keys as $key_r) {
				if (is_array($object)) {
					unset($objects[$key][$key_r]);
				} else {
					unset($objects[$key]->$key_r);
				}
			}
		}
		return $objects;
	}

	private function array_to_object($array) {
		$object = new stdClass();
		foreach ($array as $key => $value) {
			$object->$key = $value;
		}

		return $object;
	}

	private function object_to_array($object) {
		$array = [];
		foreach ($object as $key => $value) {
			$array[$key] = $value;
		}

		return $array;
	}

}
