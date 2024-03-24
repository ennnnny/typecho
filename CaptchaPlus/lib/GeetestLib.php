<?php
require_once  __DIR__.'/GeetestLibResult.php';
/**
 * sdk lib包，核心逻辑。
 *
 * @author liuquan@geetest.com
 */
class GeetestLib
{
    const IS_DEBUG = false; // 调试开关，是否输出调试日志
    const API_URL = "http://api.geetest.com";
    const REGISTER_URL = "/register.php";
    const VALIDATE_URL = "/validate.php";
    const JSON_FORMAT = "1";
    const NEW_CAPTCHA = true;
    const HTTP_TIMEOUT_DEFAULT = 5; // 单位：秒
    const VERSION = "php-laravel:3.1.0";
    const GEETEST_CHALLENGE = "geetest_challenge"; // 极验二次验证表单传参字段 chllenge
    const GEETEST_VALIDATE = "geetest_validate"; // 极验二次验证表单传参字段 validate
    const GEETEST_SECCODE = "geetest_seccode"; // 极验二次验证表单传参字段 seccode

    public function __construct($geetest_id, $geetest_key)
    {
        $this->geetest_id = $geetest_id;  // 公钥
        $this->geetest_key = $geetest_key;  // 私钥
        $this->libResult = new GeetestLibResult();
    }

    public function gtlog($message)
    {
        if (self::IS_DEBUG) {
            \Log::info("gtlog: " . $message);
        }
    }

    /**
     * bypass降级模式，检测到极验云状态异常，走本地初始化
     */
    public function localInit(){
        $this->gtlog(sprintf("localInit(): 开始本地初始化, 后续流程走宕机模式."));
        $this->buildRegisterResult(null,null);
        $this->gtlog(sprintf("localInit(): 本地初始化, lib包返回信息=%s.",$this->libResult));
        return $this->libResult;
    }

    /**
     * 验证初始化
     */
    public function register($digestmod, $params)
    {
        $this->gtlog(sprintf("register(): 开始验证初始化, digestmod=%s.", $digestmod));
        $origin_challenge = $this->requestRegister($params);
        $this->buildRegisterResult($origin_challenge, $digestmod);
        $this->gtlog(sprintf("register(): 验证初始化, lib包返回信息=%s.", $this->libResult));
        return $this->libResult;
    }

    /**
     * 向极验发送验证初始化的请求，GET方式
     */
    private function requestRegister($params)
    {
        $params = array_merge($params, ["gt" => $this->geetest_id, "sdk" => self::VERSION, "json_format" => self::JSON_FORMAT]);
        $register_url = self::API_URL . self::REGISTER_URL;
        $this->gtlog(
            sprintf("requestRegister(): 验证初始化, 向极验发送请求, url=%s, params=%s.", $register_url, json_encode($params))
        );
        $origin_challenge = null;
        try {
            $resBody = $this->httpGet($register_url, $params);
            $this->gtlog(sprintf("requestRegister(): 验证初始化, 与极验网络交互正常, 返回body=%s.", $resBody));
            $res_array = json_decode($resBody, true);
            $origin_challenge = $res_array["challenge"];
        } catch (\Throwable $t) {
            $this->gtlog("requestRegister(): 验证初始化, 请求异常，后续流程走宕机模式, " . $t->getMessage());
            $origin_challenge = "";
        }
        return $origin_challenge;
    }

    /**
     * 构建验证初始化返回数据
     */
    private function buildRegisterResult($origin_challenge, $digestmod)
    {
        // origin_challenge为空或者值为0代表失败
        if (empty($origin_challenge)) {
            // 本地随机生成32位字符串
            $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
            $challenge = '';
            for ($i = 0; $i < 32; $i++) {
                $challenge .= $characters[rand(0, strlen($characters) - 1)];
            }
            $this->libResult->setAll(
                0,
                json_encode(
                    [
                        "success" => 0,
                        "gt" => $this->geetest_id,
                        "challenge" => $challenge,
                        "new_captcha" => self::NEW_CAPTCHA
                    ]
                ),
                "请求极验register接口失败，后续流程走宕机模式"
            );
        } else {
            $challenge = null;
            if ($digestmod === "md5") {
                $challenge = $this->md5_encode($origin_challenge . $this->geetest_key);
            } elseif ($digestmod === "sha256") {
                $challenge = $this->sha256_encode($origin_challenge . $this->geetest_key);
            } elseif ($digestmod === "hmac-sha256") {
                $challenge = $this->hmac_sha256_encode($origin_challenge, $this->geetest_key);
            } else {
                $challenge = $this->md5_encode($origin_challenge . $this->geetest_key);
            }
            $this->libResult->setAll(
                1,
                json_encode(
                    [
                        "success" => 1,
                        "gt" => $this->geetest_id,
                        "challenge" => $challenge,
                        "new_captcha" => self::NEW_CAPTCHA
                    ]
                ),
                ""
            );
        }
    }

    /**
     * 正常流程下（即验证初始化成功），二次验证
     */
    public function successValidate($challenge, $validate, $seccode, $params)
    {
        $this->gtlog(
            sprintf(
                "successValidate(): 开始二次验证 正常模式, challenge=%s, validate=%s, seccode=%s.",
                $challenge,
                $validate,
                $seccode
            )
        );
        if (!$this->checkParam($challenge, $validate, $seccode)) {
            $this->libResult->setAll(0, "", "正常模式，本地校验，参数challenge、validate、seccode不可为空");
        } else {
            $response_seccode = $this->requestValidate($challenge, $validate, $seccode, $params);
            if (empty($response_seccode)) {
                $this->libResult->setAll(0, "", "请求极验validate接口失败");
            } elseif ($response_seccode === "false") {
                $this->libResult->setAll(0, "", "极验二次验证不通过");
            } else {
                $this->libResult->setAll(1, "", "");
            }
        }
        $this->gtlog(sprintf("successValidate(): 二次验证 正常模式, lib包返回信息=%s.", $this->libResult));
        return $this->libResult;
    }

    /**
     * 异常流程下（即验证初始化失败，宕机模式），二次验证
     * 注意：由于是宕机模式，初衷是保证验证业务不会中断正常业务，所以此处只作简单的参数校验，可自行设计逻辑。
     */
    public function failValidate($challenge, $validate, $seccode)
    {
        $this->gtlog(
            sprintf(
                "failValidate(): 开始二次验证 宕机模式, challenge=%s, validate=%s, seccode=%s.",
                $challenge,
                $validate,
                $seccode
            )
        );
        if (!$this->checkParam($challenge, $validate, $seccode)) {
            $this->libResult->setAll(0, "", "宕机模式，本地校验，参数challenge、validate、seccode不可为空.");
        } else {
            $this->libResult->setAll(1, "", "");
        }
        $this->gtlog(sprintf("failValidate(): 二次验证 宕机模式, lib包返回信息=%s.", $this->libResult));
        return $this->libResult;
    }

    /**
     * 向极验发送二次验证的请求，POST方式
     */
    private function requestValidate($challenge, $validate, $seccode, $params)
    {
        $params = array(
            "seccode" => $seccode,
            "json_format" => self::JSON_FORMAT,
            "challenge" => $challenge,
            "sdk" => self::VERSION,
            "captchaid" => $this->geetest_id
        );
        $validate_url = self::API_URL . self::VALIDATE_URL;
        $this->gtlog(
            sprintf("requestValidate(): 二次验证 正常模式, 向极验发送请求, url=%s, params=%s.", $validate_url, json_encode($params))
        );
        $response_seccode = null;
        try {
            $resBody = $this->httpPost($validate_url, $params);
            $this->gtlog(sprintf("requestValidate(): 二次验证 正常模式, 与极验网络交互正常, 返回body=%s.", $resBody));
            $res_array = json_decode($resBody, true);
            $response_seccode = $res_array["seccode"];
        } catch (\Throwable $t) {
            $this->gtlog("requestValidate(): 二次验证 正常模式, 请求异常, " . $t->getMessage());
            $response_seccode = "";
        }
        return $response_seccode;
    }

    /**
     * 校验二次验证的三个参数，校验通过返回true，校验失败返回false
     */
    private function checkParam($challenge, $validate, $seccode)
    {
        return !(empty($challenge) || ctype_space($challenge) || empty($validate) || ctype_space(
                $validate
            ) || empty($seccode) || ctype_space($seccode));
    }

    /**
     * 发送GET请求，获取服务器返回结果
     */
    public function httpGet($url, $params)
    {
        $url .= "?" . http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::HTTP_TIMEOUT_DEFAULT); // 设置连接主机超时（单位：秒）
        curl_setopt($ch, CURLOPT_TIMEOUT, self::HTTP_TIMEOUT_DEFAULT); // 允许 cURL 函数执行的最长秒数（单位：秒）
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    /**
     * 发送POST请求，获取服务器返回结果
     */
    public function httpPost($url, $param)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::HTTP_TIMEOUT_DEFAULT); // 设置连接主机超时（单位：秒）
        curl_setopt($ch, CURLOPT_TIMEOUT, self::HTTP_TIMEOUT_DEFAULT); // 允许 cURL 函数执行的最长秒数（单位：秒）
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-type:application/x-www-form-urlencoded"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    /**
     * md5 加密
     */
    private function md5_encode($value)
    {
        return hash("md5", $value);
    }

    /**
     * sha256加密
     */
    public function sha256_encode($value)
    {
        return hash("sha256", $value);
    }

    /**
     * hmac-sha256 加密
     */
    public function hmac_sha256_encode($value, $key)
    {
        return hash_hmac('sha256', $value, $key);
    }

}
