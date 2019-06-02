<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Datatables {
    protected $CI;
    private $csrfEnable = FALSE;
    private $table;
    private $column;
    private $column_search = array();
    private $order = array();
    private $joins = array();
    private $where = array();
    private $queryCount;
    private $type = null;

    public function __construct($config = array())
    {
        $this->CI =& get_instance();
        $this->csrfEnable = $this->CI->config->item('csrf_protection');
        if(!empty($config['ui_type']))
            $this->type = $config['ui_type'];
    }

    protected function balanceChars($str, $open, $close)
    {
        $openCount = substr_count($str, $open);
        $closeCount = substr_count($str, $close);
        $retval = $openCount - $closeCount;
        return $retval;
    }

    protected function explode($delimiter, $str, $open='(', $close=')') 
    {
        $retval = array();
        $hold = array();
        $balance = 0;
        $parts = explode($delimiter, $str);
        foreach ($parts as $part){
            $hold[] = $part;
            $balance += $this->balanceChars($part, $open, $close);
            if ($balance < 1){
                $retval[] = implode($delimiter, $hold);
                $hold = array();
                $balance = 0;
            }
        }

        if (count($hold) > 0)
            $retval[] = implode($delimiter, $hold);

        return $retval;
    }

    public function select($columns)
    {
        $this->column = $columns;
        $columns = $this->explode(',', $columns);
        foreach($columns as $val){
            $search = trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$1', $val));
            if(strpos($search, "FROM") === FALSE){
                $this->column_search[] = $search;
            }
        }
    }

    public function from($table){
        $this->table = $table;
    }

    public function join($table, $fk, $type = NULL)
    {
        $this->joins[] = array($table, $fk, $type);
    }

    public function where($key_condition, $val = NULL)
    {
        $this->where[] = array($key_condition, $val, 'and');
    }

    public function or_where($key_condition, $val = NULL)
    {
        $this->where[] = array($key_condition, $val, 'or');
    }

    public function order_by($column, $order = 'ASC')
    {
        if(is_array($column)){
            $this->order = $column;
        }
        else{
            $this->order[$column] = $order;
        }
    }

    protected function _get_datatables_query()
    {
        $searchPost = $this->CI->input->post('search',true);

        if(!empty($this->column))
            $this->CI->db->select($this->column);

        if(!empty($this->joins))
            foreach($this->joins as $val)
                $this->CI->db->join($val[0], $val[1], $val[2]);

        if(!empty($this->where)){
            foreach($this->where as $val){
                if($val[2] == 'and'){
                    $this->CI->db->where($val[0],$val[1]);
                }
                else{
                    $this->CI->db->or_where($val[0], $val[1]);
                }
            }
        }

        $tmpQueryCount = str_replace($this->column, 'COUNT(1)', str_replace('`', '', $this->CI->db->get_compiled_select($this->table, false)));

        if($searchPost['value']){
            $i = 0;
            foreach ($this->column_search as $item){
                if($i===0){
                    $this->CI->db->group_start();
                    $this->CI->db->like($item, $searchPost['value']);
                }
                else{
                    $this->CI->db->or_like($item, $searchPost['value']);
                }

                if(count($this->column_search) - 1 == $i)
                    $this->CI->db->group_end();

                $i++;
            }

            $this->queryCount = str_replace($this->column, '('.$tmpQueryCount.') as total_all, COUNT(1) as total_filtered', str_replace('`', '', $this->CI->db->get_compiled_select('', false)));
        }
        else{
            $this->queryCount = "SELECT COUNT(1) total_all, COUNT(1) total_filtered ".substr($tmpQueryCount, 16);
        }

         
        if(isset($_POST['order'])){
            $this->CI->db->order_by($this->column_search[$_POST['order']['0']['column']], $_POST['order']['0']['dir']);
        }
        else if(!empty($this->order)){
            foreach($this->order as $key => $val)
                $this->CI->db->order_by($key, $val);
        }
    }
 
    protected function get_datatables()
    {
        $this->_get_datatables_query();
        if($this->CI->input->post('length',true) != -1)
            $this->CI->db->limit($this->CI->input->post('length',true), $this->CI->input->post('start',true));
        
        return $this->CI->db->get()->result_array();
    }
 
    protected function count_total()
    {
        return $this->CI->db->query($this->queryCount)->row_array();
    }

    public function generate()
    {
        $list = $this->get_datatables();
        $total = $this->count_total();
        $data = array();
        $no = $this->CI->input->post('start',true);

        foreach ($list as $val) {
            $no++;
            $val['no'] = $no;
            $data[] = $val;
        }

        $output = array(
                    'draw' => $this->CI->input->post('draw',true),
                    'recordsTotal' => $total['total_all'],
                    'recordsFiltered' => $total['total_filtered'],
                    'data' => $data
                );

        if($this->csrfEnable)
            $output['csrf_token'] = $this->CI->security->get_csrf_hash();

        header('Content-Type: application/json');
        echo json_encode($output);
    }

    public function css($type = null)
    {
        if(!empty($this->type)){
            $type = $this->type
        }
        else{
            $this->type = $type;
        }

        switch($type){
            case 'bootstrap':
            case 'bootstrap3':
                echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/css/dataTables.bootstrap.min.css">';
            break;

            case 'bootstrap4':
                echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/css/dataTables.bootstrap4.min.css">';
            break;

            case 'foundation':
                echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/css/dataTables.foundation.min.css">';
            break;

            case 'jquery':
            case 'jqueryui':
                echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/css/dataTables.jqueryui.min.css">';
            break;

            case 'material':
            case 'materialui':
                echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/css/dataTables.material.min.css">';
            break;

            case 'semantic':
            case 'semanticui':
                echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/css/dataTables.semanticui.min.css">';
            break;

            case 'uikit':
                echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/css/dataTables.uikit.min.css">';
            break;

            default:
                echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/css/jquery.dataTables.min.css">';
            break;
        }
    }

    public function js($type = null)
    {
        if(!empty($this->type)){
            $type = $this->type
        }
        else{
            $this->type = $type;
        }

        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/js/jquery.dataTables.min.js"></script>';
        switch($type){
            case 'bootstrap':
            case 'bootstrap3':
                '<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/js/dataTables.bootstrap.min.js"></script>'
            break;

            case 'bootstrap4':
                '<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/js/dataTables.bootstrap4.min.js"></script>'
            break;

            case 'foundation':
                '<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/js/dataTables.foundation.min.js"></script>'
            break;

            case 'jquery':
            case 'jqueryui':
                '<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/js/dataTables.jqueryui.min.js"></script>'
            break;

            case 'material':
            case 'materialui':
                '<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/js/dataTables.material.min.js"></script>'
            break;

            case 'semantic':
            case 'semanticui':
                '<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/js/dataTables.semanticui.min.js"></script>'
            break;

            case 'uikit':
                '<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/js/dataTables.uikit.min.js"></script>'
            break;
        }
    }

    public function generate_js($idDom, $url, $config = array())
    {
        echo 'var '.str_replace(array('-','_'), array('',''), $idDom);
        if($this->csrfEnable)
            echo 'var csrfData = "'.$this->CI->security->get_csrf_hash().'";';
        echo '$.fn.dataTable.ext.errMode = "none";';
        echo '$(document).ready(function(){';
        echo str_replace(array('-','_'), array('',''), $idDom).' = $("#'.$idDom.'").DataTable({';
        echo 'processing: true,';
        echo 'serverSide: true,';
        echo 'ajax: {';
        echo 'url: "'.$url.'",';
        echo 'type: "POST",';
        if($this->csrfEnable){
            echo 'data: function(d){';
            echo 'return $.extend( {}, d, {';
            echo '"'.$this->CI->security->get_csrf_token_name().'": csrfData';
            echo '});';
            echo '},';
            echo 'dataSrc:function(response) {';
            echo 'csrfData = response.csrf_token;';
            echo 'return response.data;';
            echo '},';
            echo '},';
        }
        echo 'ordering: '.(($config['ordering'])?true:false).',';
        echo 'searching: '.(($config['searching'])?true:false).',';
        echo 'pageLength: '.((!empty($config['pageLength']))?$config['pageLength']:25).',';
        echo 'responsive: '.(($config['responsive'])?true:false).',';
        echo ((!empty($config['language']))?'language: '.json_encode($config['language']).',':null);
        if(!empty($config['order'])){
            $tmpI = 1;
            echo 'order: [';
            foreach($config['order'] as $key => $value){
                if(count($config['order']) == $tmpI){
                    echo '['.$key.', "'.$value.'"]';
                }
                else{
                    echo '['.$key.', "'.$value.'"],';
                }
                $tmpI++;
            }
            echo '],';
        }
        else{
            echo 'order: [],';
        }
        echo 'columns: [';
        $tmpI = 1;
        foreach($config['columns'] as $result){
            echo '{ ';
            $tmpJ = 1;

            foreach($result as $key => $val){
                if(count($result) == $tmpJ){
                    if($key == 'mRender'){
                        echo $key.': '.$val;
                    }
                    else{
                        echo $key.': '.((gettype($val) == 'string')?'"'.$val.'"':$val);
                    }
                }
                else{
                    if($key == 'mRender'){
                        echo $key.': '.$val.',';
                    }
                    else{
                        echo $key.': '.((gettype($val) == 'string')?'"'.$val.'"':$val).',';
                    }
                }
                $tmpJ++;
            }

            if(count($config['columns']) == $tmpI){
                echo ' }';
            }
            else{
                echo ' },';
            }
            $tmpI++;
        }
        if(!empty($config['columnDefs'])){
            echo '],';
            echo 'columnDefs:[';
            $tmpI = 1;
            foreach($config['columnDefs'] as $result){
                echo '{';
                $tmpJ = 1;
                foreach($result as $key => $val){
                    if(count($result) == $tmpJ){
                        if($key == 'targets'){
                            echo $key.': ['.$val.']';
                        }
                        elseif($key == 'render'){
                            echo $key.': '.$val;
                        }
                        else{
                            echo $key.': '.((gettype($val) == 'string')?'"'.$val.'"':$val);
                        }
                    }
                    else{
                        if($key == 'targets'){
                            echo $key.': ['.$val.'],';
                        }
                        elseif($key == 'render'){
                            echo $key.': '.$val.',';
                        }
                        else{
                            echo $key.': '.((gettype($val) == 'string')?'"'.$val.'"':$val).',';
                        }
                    }
                    $tmpJ++;
                }
                if(count($config['columnDefs']) == $tmpI){
                    echo '}';
                }
                else{
                    echo '},';
                }
            }
            echo ']';
        }
        else{
            echo ']';
        }
        echo '});';
        echo '});';
    }
}