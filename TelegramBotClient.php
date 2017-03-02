<?php


abstract class TBClient
{
    private $BOT_TOKEN;
    private $API_URL;

    private $message;
    private $first_name;
    private $chat_id;
    private $api_result;
    private $api_result_error = false;
    private $web_hook_url;

    protected $markup = false;
    protected $command;

    static public $SEND_MESSAGE      = 'sendMessage';
    static public $GET_WEB_HOOK_INFO = 'getWebhookInfo';
    static public $SET_WEB_HOOK      = 'setWebhook';
    static public $GET_BOT_INFO      = 'getMe';
    static public $GET_UPDATES       = 'getUpdates';

    function __construct($token, $web_hook_url)
    {
        $this->checkType($token, 'string');

        $this->BOT_TOKEN = $token;
        $this->API_URL = 'https://api.telegram.org/bot' . $this->BOT_TOKEN . '/';
        $this->web_hook_url = $web_hook_url;

        if (!$this->isSetWebHook()) {
            $this->setWebHook();
        }

    }

    /**
     * @param mixed $first_name
     */
    public function setFirstName($first_name)
    {
        $this->first_name = $first_name;
    }

    /**
     * @param mixed $chat_id
     */
    public function setChatId($chat_id)
    {
        $this->chat_id = $chat_id;
    }

    /**
     * @param string $message
     */
    private function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @return mixed
     */
    protected function getMessage()
    {
        return $this->message;
    }

    /**
     * @return mixed
     */
    protected function getFirstName()
    {
        return $this->first_name;
    }

    /**
     * @return mixed
     */
    protected function getChatId()
    {
        return $this->chat_id;
    }

    /**
     * @param $command
     */
    private function setCommand($command)
    {
        $this->command = $command;
    }

    /**
     * @param $command
     * @return mixed
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param $keyboard
     * @param bool $one_time_keyboard
     * @param bool $resize_keyboard
     * @param int $row_width
     *
     *  'keyboard' => array
     *  'one_time_keyboard' => bool
     *  'resize_keyboard' => bool
     *  'row_width' => int
     */
    public function CreateReplyKeyboard($keyboard, $one_time_keyboard = false, $resize_keyboard = true)
    {
        $this->checkType($keyboard, 'array');

        $k = array();
        foreach ($keyboard as $v) $k[] = array($v);

        $this->markup = array(
            "keyboard" => $k,
            "one_time_keyboard" => $one_time_keyboard,
            "resize_keyboard" => $resize_keyboard,
        );
    }

    /**
     * @param $element
     * @param $type_value
     */
    protected function checkType($element, $type_value)
    {
        if (gettype($element) != $type_value) {
            new \Exception("Wrong type {$type_value}");
        }
    }

    public function log($msg)
    {
        file_put_contents('./l.txt', $msg . PHP_EOL, FILE_APPEND);
    }


    private function Request($parameters)
    {

        $this->checkType($parameters, 'array');

        $parameters["method"] = $this->getCommand();

        if (!$this->markup) {
            unset($parameters['reply_markup']);
        }

        $cl = curl_init($this->API_URL);
        curl_setopt($cl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($cl, CURLOPT_TIMEOUT, 60);
        curl_setopt($cl, CURLOPT_POSTFIELDS, json_encode($parameters));
        curl_setopt($cl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

        $result = curl_exec($cl);
        curl_close($cl);
        echo $result;
        $this->log($result);

        $this->markup = array();

        $this->api_result = json_decode($result);


        //{"ok":false,"error_code":404,"description":"Not Found: method not found"}

        if ($this->api_result->ok) {
            $this->api_result = $this->api_result->result;
        } else {
            $this->api_result_error = 'Error code:' . $this->api_result->error_code . ' ' . $this->api_result->description;
            //send log
        }

    }


    public function Command($command)
    {
        $this->checkType($command, 'string');
        $this->setCommand($command);

        return $this;
    }

    /**
     * @param array $parametersarray_merge (array(
     * 'chat_id' => $this->chat_id,
     * "text" => $message,
     * 'parse_mode' => 'Markdown',
     * 'reply_markup' => $this->markup
     *
     */

    /**
     * @param array $parameters
     */
    public function Send($parameters = array())
    {
        $this->Request(
            $parameters
        );
    }


    /**
     * @return bool
     */
    protected function isSetWebHook()
    {
        $this->Command(TBClient::$GET_WEB_HOOK_INFO)->send();

        if ($this->api_result_error) return false;
        return (!empty($this->api_result->url)) ? true : false;

    }

    /**
     * @return bool
     */
    protected function setWebHook()
    {
        $this->Command(TBClient::$SET_WEB_HOOK)->send(array(
            'url' => $this->web_hook_url
        ));

        if (!$this->api_result_error) return true;
    }


    public function sendMessage($message)
    {
        $this->Command(TBClient::$SEND_MESSAGE)->send(
            array(
                'chat_id' => $this->getChatId(),
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => $this->markup
            )
        );
    }


    public function botExecute()
    {

        $output = json_decode(file_get_contents('php://input'), TRUE);
        $chat_id = $output['message']['from']['id'];
        $first_name = $output['message']['from']['first_name'];
        $message = $output['message']['text'];

        $this->setChatId($chat_id);
        $this->setFirstName($first_name);
        $this->setMessage($message);


        $this->message = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
            return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
        }, $this->message);


        $this->message = mb_convert_case($this->message, MB_CASE_LOWER, "UTF-8");
    }


    protected function handlerCommand($command_name, $handler)
    {

        call_user_func($handler);
    }

    abstract public function bot();
}