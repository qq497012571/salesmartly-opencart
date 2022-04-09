<?php

use Braintree\Exception\ServerError;

class ControllerExtensionModuleSaleSmartly extends Controller
{
    /** @var array  */
    protected $error = [];

    const AUTH_URI = 'http://dev.adsweb.com/index.php/shop/opencart/auth';
    const UNINSTALL_URI = 'http://dev.adsweb.com/index.php/shop/opencart/uninstall';
    const PlUGIN_URI = 'http://dev.adsweb.com/index.php/sys/user/passport/third-account-login';
    const LOGIN_URI = 'https://app-dev.salesmartly.com/auth/shop?source=opencart&shop_id=%s&language=en&encryption=%s';

    const LOGIN_PATH = 'extension/module/salesmartly/login';
    const BASE_PATH = 'extension/module/salesmartly';

    //fields
    const FIELD_ENCRYPTION = 'salesmartly_encryption';
    const FIELD_ENCRYPTION_EXPIRE_TIME = 'salesmartly_encryption_expire_time';
    const FIELD_SCRIPT = 'salesmartly_script';
    const FIELD_STATUS = 'salesmartly_status';

    /** @var array */
    protected $dataFields =[
        self::FIELD_ENCRYPTION,
        self::FIELD_ENCRYPTION_EXPIRE_TIME,
        self::FIELD_SCRIPT,
        self::FIELD_STATUS
    ];

    /** @var array */
    protected $translations = [
        'heading_title',
        'text_edit',
        'text_enabled',
        'text_disabled',
        'entry_status',
        'button_save',
        'button_cancel',
        'entry_email',
        'entry_userPassword',
        'entry_userDisplayName',
        'entry_helpm',
        'entry_helpp',
        'entry_up_text',
        'entry_up_text2',
        'entry_down_text',
        'error_email_validate',
    ];
    /**
     * @throws Exception
     */
    public function index()
    {
        $this->load->language(self::BASE_PATH);

        $this->load->model('setting/setting');

        $this->document->setTitle($this->language->get('heading_title2'));
        
        $lang_p = substr($this->config->get('config_admin_language'), 0, 2);

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSettingValue('salesmartly', self::FIELD_STATUS, $this->request->post[self::FIELD_STATUS]);
        }

        $data = array_merge(
            $this->getTranslates(),
            [
                'entry_siteUrl' => HTTPS_CATALOG,
                'salesmartly_partnerId' =>  'opencart',
                'response_error' =>  !empty($this->error['response_error']) ? $this->error['response_error'] : '',
                'error_warning' =>  !empty($this->error['warning']) ? $this->error['warning'] : '',
                'error_email' =>  !empty($this->error['email']) ? $this->error['email'] : '',
                'error_userPassword' =>  !empty($this->error['userPassword']) ? $this->error['userPassword'] : '',
                'header' =>  $this->load->controller('common/header'),
                'column_left' =>  $this->load->controller('common/column_left'),
                'footer' =>  $this->load->controller('common/footer'),
            ]
        );

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
            ],
            [
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link(self::BASE_PATH, 'user_token=' . $this->session->data['user_token'], true)
            ]
        ];

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['action'] = $this->url->link(self::BASE_PATH, 'user_token=' . $this->session->data['user_token'], true);

        foreach ($this->dataFields as $field) {
            $data[$field] = isset($this->request->post[$field])
                ? $this->request->post[$field]
                : $this->config->get($field);
        }

        $this->response->setOutput($this->load->view(self::BASE_PATH, $data));
    }

    public function install()
    {

        if (!$this->model_setting_event->getEventByCode('salesmartly_admin_column_left')) {

            $code = "salesmartly_admin_column_left";
            $trigger = "admin/view/common/column_left/before";
            $action = "extension/module/salesmartly/menu";
            $this->model_setting_event->addEvent($code, $trigger, $action);

            $code = "salesmartly_footer";
            $trigger = "catalog/view/common/footer/before";
            $action = "extension/module/salesmartly/footer";
            $this->model_setting_event->addEvent($code, $trigger, $action);

            $code = "salesmartly_header";
            $trigger = "catalog/view/common/header/before";
            $action = "extension/module/salesmartly/header";
            $this->model_setting_event->addEvent($code, $trigger, $action);

            $domain = $this->request->server['SERVER_NAME'];
            $shopId = md5($domain);
            // 请求授权
            $result = $this->request(self::AUTH_URI, http_build_query(['shop_id' => $shopId, 'domain' => $domain]));
            $auth = $result['data'];
            // 授权成功, 创建账号
            if (isset($auth['encryption'])) {
                $re = 3;
                while (empty($login['data']['plugin_script'])) {
                    if ($re == 0) {
                        break;
                    }
                    $login = $this->request(self::PlUGIN_URI, http_build_query(['shop_id' => $shopId, 'source' => 'opencart', 'encryption' => $auth['encryption']]));
                    $re--;
                    sleep(1);
                }
            }

            if (!isset($login['data']['plugin_script']) || empty($login['data']['plugin_script'])) {
                throw new ServerError('install timeout!');
            }

            $config = [
                'salesmartly_status' => 0,
                'salesmartly_shopid' => $shopId,
                'salesmartly_domain' => $domain,
                'salesmartly_encryption' => $auth['encryption'],
                'salesmartly_encryption_expire_time' => $auth['encryption_expire_time'],
                'salesmartly_script' => $login['data']['plugin_script'],
            ];

            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('salesmartly', $config);
        }

    }

    public function uninstall()
    {
        $this->model_setting_event->deleteEventByCode('salesmartly_admin_column_left');
        $this->model_setting_event->deleteEventByCode('salesmartly_footer');
        $this->model_setting_event->deleteEventByCode('salesmartly_header');
        $shopId = $this->config->get('salesmartly_shopid');
        $domain = $this->config->get('salesmartly_domain');
        $this->request(self::UNINSTALL_URI, http_build_query(['shop_id' => $shopId, 'domain' => $domain]));
    }

    public function login()
    {
        $this->load->language(self::BASE_PATH);

        $this->load->model('setting/setting');

        $data['heading_title'] = $this->language->get('heading_title2');

        if (!$this->user->hasPermission('access', self::BASE_PATH)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['response_error'])) {
            $data['response_error'] = $this->error['response_error'];
        } else {
            $data['response_error'] = '';
        }

        $data['language'] = substr($this->config->get('config_admin_language'), 0, 2);

        $data['text_edit'] = $this->language->get('text_edit2');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['button_nastr'] = $this->language->get('button_nastr');
        $data['button_setup'] = $this->language->get('button_setup');
        $data['button_newwind'] = $this->language->get('button_newwind');
        $data['button_newwind2'] = $this->language->get('button_newwind2');

        list($encryption, ) = $this->refreshAuth();

        $shopId = $this->config->get('salesmartly_shopid');
        $data['salesmartly_login_url'] = sprintf(self::LOGIN_URI, $shopId, $encryption);
        $this->response->setOutput($this->load->view('extension/module/salesmartly_login', $data));
    }

    /**
     * @param $route
     * @param $data
     * @param $output
     */
    public function menu(&$route, &$data, &$output)
    {
        if ($this->user->hasPermission('access', self::BASE_PATH)) {
            $data['menus'][] = array(
                'id'       => 'menu-salesmartly',
                'icon'	   => 'fa-dashboard',
                'name'	   => 'salesmartly',
                'href'     => $this->url->link(self::LOGIN_PATH, 'user_token=' . $this->session->data['user_token'], true),
                'children' => array()
            );
        }
    }

    /**
     * 刷新auth密钥
     */
    protected function refreshAuth()
    {
        $shopId = $this->config->get('salesmartly_shopid');
        $domain = $this->config->get('salesmartly_domain');
        $result = $this->request(self::AUTH_URI, http_build_query(['shop_id' => $shopId, 'domain' => $domain]));
        $auth = $result['data'];
        if (isset($auth['encryption'])) {
            $this->model_setting_setting->editSettingValue('salesmartly', self::FIELD_ENCRYPTION, $auth['encryption']);
            $this->model_setting_setting->editSettingValue('salesmartly', self::FIELD_ENCRYPTION_EXPIRE_TIME, $auth['encryption_expire_time']);
        }
        return [$auth['encryption'], $auth['encryption_expire_time']];
    }

    /**
     * @param $content
     * @return bool|mixed|string
     * @throws Exception
     */
    protected function request($url, $content)
    {
        $useCurl = true;

        if (ini_get('allow_url_fopen')) {
            $useCurl = false;
        } elseif (!extension_loaded('curl') || !dl('curl.so')) {
            $useCurl = false;
        }

        if (!extension_loaded('openssl')) {
            $url = str_replace('https:', 'http:', $url);
        }
        if ($useCurl && $curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
            $response = curl_exec($curl);
            curl_close($curl);
        } else {
            $response = file_get_contents(
                $url,
                false,
                stream_context_create(
                    array(
                        'http' => array(
                            'method' => 'POST',
                            'header' => 'Content-Type: application/x-www-form-urlencoded',
                            'content' => $content
                        )
                    )
                )
            );
        }

        if (empty($response)) {
            throw new Exception('Cannot get response from salesmartly');
        }

        return json_decode($response, true);
    }

    /**
     * @return array
     */
    protected function getTranslates()
    {
        $translates = [];
        foreach ($this->translations as $translation) {
            $translates[$translation] = $this->language->get($translation);
        }

        return $translates;
    }

    /**
     * @return bool
     */
    protected function validate()
    {
        if (!$this->user->hasPermission('modify', self::BASE_PATH)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
}