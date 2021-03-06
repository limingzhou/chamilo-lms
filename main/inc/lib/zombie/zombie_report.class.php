<?php

/**
 * Description of zombie_report
 *
 * @copyright (c) 2012 University of Geneva
 * @license GNU General Public License - http://www.gnu.org/copyleft/gpl.html
 * @author Laurent Opprecht <laurent@opprecht.info>
 */
class ZombieReport implements Countable
{
    /**
     * @return ZombieReport
     */
    public static function create($additional_parameters = [])
    {
        return new self($additional_parameters);
    }

    protected $additional_parameters = [];

    public function __construct($additional_parameters = [])
    {
        $this->additional_parameters = $additional_parameters;
    }

    public function get_additional_parameters()
    {
        return $this->additional_parameters;
    }

    public function get_parameters()
    {
        $result = [
            'name' => 'zombie_report_parameters',
            'method' => 'GET',
            'attributes' => ['class' => 'well form-horizontal form-search'],
            'items' => [
                [
                    'name' => 'ceiling',
                    'label' => get_lang('LastAccess'),
                    'type' => 'date_picker',
                    'default' => $this->get_ceiling('Y-m-d'),
                    'rules' => [
//                        array(
//                            'type' => 'required',
//                            'message' => get_lang('Required')
//                        ),
                        [
                            'type' => 'date',
                            'message' => get_lang('Date')
                        ]
                    ]
                ],
                [
                    'name' => 'active_only',
                    'label' => get_lang('ActiveOnly'),
                    'type' => 'checkbox',
                    'default' => $this->get_active_only()
                ],
                [
                    'name' => 'submit_button',
                    'type' => 'button',
                    'value' => get_lang('Search'),
                    'attributes' => ['class' => 'search']
                ]
            ]
        ];
        $additional_parameters = $this->get_additional_parameters();
        foreach ($additional_parameters as $key => $value) {
            $result['items'][] = [
                'type' => 'hidden',
                'name' => $key,
                'value' => $value
            ];
        }
        return $result;
    }

    protected $parameters_form = null;

    /**
     *
     * @return FormValidator
     */
    public function get_parameters_form()
    {
        $parameters = $this->get_parameters();
        if (empty($parameters)) {
            return null;
        }
        if (empty($this->parameters_form)) {
            $this->parameters_form = new FormValidator(
                $parameters['name'],
                $parameters['method'],
                null,
                null,
                $parameters['attributes']
            );
        }

        return $this->parameters_form;
    }

    public function display_parameters($return = false)
    {
        $form = $this->get_parameters_form();
        if (empty($form)) {
            return '';
        }

        $result = $form->returnForm();
        if ($return) {
            return $result;
        } else {
            echo $result;
        }
    }

    public function is_valid()
    {
        $form = $this->get_parameters_form();
        if (empty($form)) {
            return true;
        }
        return $form->isSubmitted() == false || $form->validate();
    }

    public function get_ceiling($format = null)
    {
        $result = Request::get('ceiling');
        $result = $result ? $result : ZombieManager::last_year();

        $result = is_array($result) && count($result) == 1 ? reset($result) : $result;
        $result = is_array($result) ? mktime(0, 0, 0, $result['F'], $result['d'], $result['Y']) : $result;
        $result = is_numeric($result) ? (int) $result : $result;
        $result = is_string($result) ? strtotime($result) : $result;
        if ($format) {
            $result = date($format, $result);
        }
        return $result;
    }

    public function get_active_only()
    {
        $result = Request::get('active_only', false);
        $result = $result === 'true' ? true : $result;
        $result = $result === 'false' ? false : $result;
        $result = (bool) $result;
        return $result;
    }

    public function get_action()
    {

        /**
         * todo check token
         */
        $check = Security::check_token('post');
        Security::clear_token();
        if (!$check) {
            return 'display';
        }
        return Request::post('action', 'display');
    }

    public function perform_action()
    {
        $ids = Request::post('id');
        if (empty($ids)) {
            return $ids;
        }

        $action = $this->get_action();
        $f = [$this, 'action_'.$action];
        if (is_callable($f)) {
            return call_user_func($f, $ids);
        }
        return false;
    }

    public function action_deactivate($ids)
    {
        return UserManager::deactivate_users($ids);
    }

    public function action_activate($ids)
    {
        return UserManager::activate_users($ids);
    }

    public function action_delete($ids)
    {
        return UserManager::delete_users($ids);
    }

    public function count()
    {
        if (!$this->is_valid()) {
            return 0;
        }

        $ceiling = $this->get_ceiling();
        $active_only = $this->get_active_only();
        $items = ZombieManager::listZombies($ceiling, $active_only);
        return count($items);
    }

    public function get_data($from, $count, $column, $direction)
    {
        if (!$this->is_valid()) {
            return [];
        }

        $ceiling = $this->get_ceiling();
        $active_only = $this->get_active_only();

        $items = ZombieManager::listZombies($ceiling, $active_only, $count, $from, $column, $direction);
        $result = [];
        foreach ($items as $item) {
            $row = [];
            $row[] = $item['user_id'];
            $row[] = $item['code'];
            $row[] = $item['firstname'];
            $row[] = $item['lastname'];
            $row[] = $item['username'];
            $row[] = $item['email'];
            $row[] = $item['status'];
            $row[] = $item['auth_source'];
            $row[] = api_format_date($item['registration_date'], DATE_FORMAT_SHORT);
            $row[] = api_format_date($item['login_date'], DATE_FORMAT_SHORT);
            $row[] = $item['active'];
            $result[] = $row;
        }
        return $result;
    }

    public function display_data($return = false)
    {
        $count = [$this, 'count'];
        $data = [$this, 'get_data'];

        $parameters = [];
        $parameters['sec_token'] = Security::get_token();
        $parameters['ceiling'] = $this->get_ceiling();
        $parameters['active_only'] = $this->get_active_only() ? 'true' : 'false';
        $additional_parameters = $this->get_additional_parameters();
        $parameters = array_merge($additional_parameters, $parameters);

        $table = new SortableTable('users', $count, $data, 1, 50);
        $table->set_additional_parameters($parameters);

        $col = 0;
        $table->set_header($col++, '', false);
        $table->set_header($col++, get_lang('Code'));
        $table->set_header($col++, get_lang('FirstName'));
        $table->set_header($col++, get_lang('LastName'));
        $table->set_header($col++, get_lang('LoginName'));
        $table->set_header($col++, get_lang('Email'));
        $table->set_header($col++, get_lang('Profile'));
        $table->set_header($col++, get_lang('AuthenticationSource'));
        $table->set_header($col++, get_lang('RegisteredDate'));
        $table->set_header($col++, get_lang('LastAccess'), false);
        $table->set_header($col++, get_lang('Active'), false);

        $table->set_column_filter(5, [$this, 'format_email']);
        $table->set_column_filter(6, [$this, 'format_status']);
        $table->set_column_filter(10, [$this, 'format_active']);

        $table->set_form_actions([
            'activate' => get_lang('Activate'),
            'deactivate' => get_lang('Deactivate'),
            'delete' => get_lang('Delete')
        ]);

        if ($return) {
            return $table->return_table();
        } else {
            echo $table->return_table();
        }
    }

    /**
     * Table formatter for the active column.
     *
     * @param string $active
     * @return string
     */
    public function format_active($active)
    {
        $active = ($active == '1');
        if ($active) {
            $image = 'accept';
            $text = get_lang('Yes');
        } else {
            $image = 'error';
            $text = get_lang('No');
        }

        $result = Display::return_icon($image.'.png', $text);
        return $result;
    }

    public function format_status($status)
    {
        $statusname = api_get_status_langvars();
        return $statusname[$status];
    }

    public function format_email($email)
    {
        return Display::encrypted_mailto_link($email, $email);
    }

    public function display($return = false)
    {
        $result = $this->display_parameters($return);
        if ($this->perform_action()) {
            if ($return) {
                $result .= Display::return_message(get_lang('Done'), 'confirmation');
            } else {
                echo Display::return_message(get_lang('Done'), 'confirmation');
            }
        }
        $result .= $this->display_data($return);
        if ($return) {
            return $result;
        }
    }
}
