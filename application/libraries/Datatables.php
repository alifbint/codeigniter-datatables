<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
* =============================================
* Datatables for Codeigniter 3.x
* Version           : 1.0
* Modified By       : Alif Bintoro <alifbintoro77@gmail.com>
*
* Original Source   : Ignited Datatables
* URL               : https://github.com/IgnitedDatatables/Ignited-Datatables
* Created By        : Philip Sturgeon <email@philsturgeon.co.uk>
* =============================================
*/

class Datatables {
    protected $CI;
    protected $csrfEnable = FALSE;
    protected $table;
    protected $column;
    protected $column_search = array();
    protected $order = array();
    protected $joins = array();
    protected $where = array();
    protected $group_by = null;
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
            $search = trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$1', $val));
            if(strpos($search, "FROM") === FALSE){
                $this->column_search[] = $search;
            }
        }

        return $this;
    }

    public function from($table){
        $this->table = $table;

        return $this;
    }

    public function join($table, $fk, $type = NULL)
    {
        $this->joins[] = array($table, $fk, $type);

        return $this;
    }

    public function where($key_condition, $val = NULL)
    {
        $this->where[] = array($key_condition, $val, 'and');

        return $this;
    }

    public function or_where($key_condition, $val = NULL)
    {
        $this->where[] = array($key_condition, $val, 'or');

        return $this;
    }

    public function order_by($column, $order = 'ASC')
    {
        if(is_array($column)){
            $this->order = $column;
        }
        else{
            $this->order[$column] = $order;
        }

        return $this;
    }

    public function group_by($group_by)
    {
        $this->group_by = $group_by;

        return $this;
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

        if(!empty($this->group_by))
            $this->CI->db->group_by($this->group_by);

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
 
    public function count_total()
    {
        return $this->CI->db->query($this->queryCount)->row_array();
    }

    public function generate($raw = false)
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

        if($raw){
            return $output;
        }
        else{
            header('Content-Type: application/json');
            echo json_encode($output);
        }
    }
}
