<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Datatables {
    protected $CI;
    protected $csrfEnable = FALSE;
    protected $table;
    protected $column;
    protected $column_search = array();
    protected $order = array();
    protected $joins = array();
    protected $where = array();
    protected $queryCount;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->csrfEnable = $this->CI->config->item('csrf_protection');
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
            $this->column_search[] = trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$1', $val));
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

        $tmpQueryCount = preg_replace("/SELECT\s(([A-Za-z_0-1,`.]+)\s)*FROM/", "SELECT COUNT(*) FROM", $this->CI->db->get_compiled_select($this->table, false));

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
        }

        $this->queryCount = preg_replace("/SELECT\s(([A-Za-z_0-1,`.]+)\s)*FROM/", "SELECT (".$tmpQueryCount.") as total_all, COUNT(*) as total_filtered FROM", $this->CI->db->get_compiled_select('', false));
         
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
}