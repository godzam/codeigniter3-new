<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @property CI_DB_query_builder $db insert_id() (used below) is declared
 *           per-driver, not on this base class — see the ignoreErrors
 *           entry for it in phpstan.neon.dist.
 */
class User_model extends CI_Model
{
    protected $table = 'users';

    public function __construct()
    {
        // No parent::__construct() — CI_Model doesn't declare one (or
        // extend anything that does), so calling it would fatal.
        // Loaded here rather than relying on the caller passing the
        // $db_conn flag to $this->load->model() — a model should own its
        // own dependency on the database, not depend on every caller
        // remembering to ask for it.
        $this->load->database();
    }

    public function find($id)
    {
        return $this->db->get_where($this->table, ['id' => $id])->row();
    }

    public function find_by_email($email)
    {
        return $this->db->get_where($this->table, ['email' => $email])->row();
    }

    public function create(array $data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->db->insert($this->table, $data);

        return (int) $this->db->insert_id();
    }
}
