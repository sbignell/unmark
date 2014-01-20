<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Users_To_Marks_model extends Plain_Model
{

    public $sort = 'created_on DESC';


	public function __construct()
    {
        // Call the Model constructor
        parent::__construct();

        // Set data types
        $this->data_types = array(
            'mark_id'     =>  'numeric',
            'user_id'     =>  'numeric',
            'label_id'    =>  'numeric',
            'notes'       =>  'string',
            'created_on'  =>  'datetime',
            'archived_on' =>  'datetime',
            'title'       =>  'string',
            'url'         =>  'url'
        );

    }

    public function create($options=array())
    {
        $valid  = validate($options, $this->data_types, array('user_id', 'mark_id'));

        // Make sure all the options are valid
        if ($valid === true) {

            $options['created_on'] = date('Y-m-d H:i:s');
            $q   = $this->db->insert_string('users_to_marks', $options);
            $res = $this->db->query($q);

            // Check for errors
            $this->sendException();

            // If good, return full record
            if ($res === true) {
                $user_mark_id = $this->db->insert_id();
                return $this->read($user_mark_id);
            }

            // Else return error
            return $this->formatErrors('The mark could not be created. Please try again.');
        }

        return $this->formatErrors($valid);
    }




    protected function formatTags($marks)
    {
        foreach ($marks as $k => $mark) {
            $marks[$k]->tags = array();
            if (isset($mark->tag_ids) && ! empty($mark->tags_ids)) {
                $ids   = explode($this->delimiter, $mark->tags_ids);
                $names = explode($this->delimiter, $mark->tag_names);
                foreach ($ids as $kk => $id) {
                    $marks[$k]->tags[$id] = $names[$kk];
                }
            }
            unset($marks[$k]->tag_ids);
            unset($marks[$k]->tag_names);
        }
        return $marks;
    }


    public function readComplete($where, $limit=1, $page=1, $start=null)
    {
        $id         = (is_numeric($where)) ? $where : null;
        $where      = (is_numeric($where)) ? 'users_to_marks.' . $this->id_column . " = '$where'" : trim($where);
        $page       = (is_numeric($page) && $page > 0) ? $page : 1;
        $limit      = ((is_numeric($limit) && $limit > 0) || $limit == 'all') ? $limit : 1;
        $start      = (! is_null($start)) ? $start : $limit * ($page - 1);
        $q_limit    = ($limit != 'all') ? ' LIMIT ' . $start . ',' . $limit : null;
        $sort       = (! empty($this->sort)) ? ' ORDER BY users_to_marks.' . $this->sort : null;

        // Stop, query time
        $q     = $this->db->query('SET SESSION group_concat_max_len = 10000');
		$marks = $this->db->query("
            SELECT
            users_to_marks.users_to_mark_id, users_to_marks.notes, users_to_marks.created_on,
            marks.mark_id, marks.title, marks.url,
            GROUP_CONCAT(tags.tag_id SEPARATOR '" . $this->delimiter . "') AS tag_ids,
            GROUP_CONCAT(tags.name SEPARATOR '" . $this->delimiter . "') AS tag_names,
            labels.label_id, labels.name AS label_name
            FROM users_to_marks
            LEFT JOIN marks ON users_to_marks.mark_id = marks.mark_id
            LEFT JOIN user_marks_to_tags ON users_to_marks.mark_id = user_marks_to_tags.users_to_mark_id
            LEFT JOIN labels ON users_to_marks.label_id = labels.label_id
            LEFT JOIN tags ON user_marks_to_tags.tag_id = tags.tag_id
            WHERE " . $where . " GROUP BY users_to_marks.users_to_mark_id" . $sort . $q_limit
        );

        // Check for errors
        $this->sendException();

        // Now format the group names and ids
        if ($marks->num_rows() > 0) {
            return $this->formatTags($marks->result());
        }

        return false;
    }

}