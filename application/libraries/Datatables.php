<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Datatables {
    protected $CI;
    protected $table;
    protected $column;
    protected $column_order = array(null);
    protected $column_search = array();
    protected $select = array();
    protected $order = array();
    protected $joins = array();
    protected $where = array();

    public function __construct()
    {
        $this->CI =& get_instance();
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
        foreach($this->explode(',', $columns) as $val){
            $column = trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$1', $val));
            $this->select[$column] =  trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$2', $val));
            $this->column_search[] =  $column;
            $this->column_order[] = $column;
        }

        $this->column = $columns;
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
        $this->where[$key_condition] = array($val, 'or');
    }

    public function order_by($ordering = array())
    {
        $this->order = $ordering;
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

         
        if(isset($_POST['order'])){
            $this->CI->db->order_by($this->column_order[$_POST['order']['0']['column']], $_POST['order']['0']['dir']);
        } 
        else if(!empty($this->order)){
            $order = $this->order;
            $this->CI->db->order_by(key($order), $order[key($order)]);
        }
    }
 
    protected function get_datatables()
    {
        $this->_get_datatables_query();
        if($this->CI->input->post('length',true) != -1)
            $this->CI->db->limit($this->CI->input->post('length',true), $this->CI->input->post('start',true));
        
        $query = $this->CI->db->get($this->table);
        return $query->result_array();
    }
 
    protected function count_filtered()
    {
        $this->_get_datatables_query();
        $query = $this->CI->db->get($this->table);
        return $query->num_rows();
    }
 
    protected function count_all()
    {
        $query = $this->CI->db->from($this->table);
        return $query->count_all_results();
    }

    public function generate()
    {
        $list = $this->get_datatables();
        $data = array();
        $no = $this->CI->input->post('start',true);
        foreach ($list as $val) {
            $no++;
            $row = array();
            $row['no'] = $no;
            foreach($this->select as $sele){
                $row[$sele] = $val[$sele];
            }
 
            $data[] = $row;
        }
 
        $output = array(
                        'draw' => $this->CI->input->post('draw',true),
                        'recordsTotal' => $this->count_all(),
                        'recordsFiltered' => $this->count_filtered(),
                        'data' => $data,
                );

        header('Content-Type: application/json');
        echo json_encode($output);
    }
}