<?php

class Blockspring {
  public static function define($my_function = null){
    global $argv;

    $request = array(
      "params" => array()
    );

    // From STDIN.
    if (!posix_isatty(STDIN)) {
      $stdin = '';
      while (false !== ($line = fgets(STDIN))) {
        $stdin .= $line;
      }

      $stdin_params = json_decode($stdin, true);

      $request["params"] = $stdin_params["data"];
    }

    // From sysargs
    $sys_args = array();
    for ($i = 1; $i < count($argv); $i++) {
      if (preg_match('/^--([^=]+)=(.*)/', $argv[$i], $match)) {
        $sys_args[$match[1]] = $match[2];
      }
    }

    foreach ($sys_args as $key => $val) {
      $request["params"][$key] = $val;
    }

    $response = new BlockspringResponse();

    // Print output
    print_r($my_function($request, $response));
  }

  public static function run($block, $data, $api_key = null) {
    $api_key = $api_key ? $api_key : getenv('BLOCKSPRING_API_KEY');

    if (!$api_key) {
      trigger_error("BLOCKSPRING_API_KEY environment variable not set.", E_USER_WARNING);
      $api_key_string = '';
    } else {
      $api_key_string = "api_key=" . $api_key;
    }

    $block_parts = explode("/", $block);
    $block = $block_parts[1];

    $url = "https://sender.blockspring.com/api_v2/blocks/{$block}?{$api_key_string}";
    $options = array(
      'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded",
        'method'  => 'POST',
        'content' => http_build_query($data),
      ),
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    return $result;
  }
}


class BlockspringResponse {
  public $result = array(
    "data" => array(),
    "files" => array(),
    "errors" => null
  );

  public function addOutput($name, $value) {
    $this->result["data"][$name] = $value;
  }

  public function addFileOutput($name, $filepath) {
    $this->result["files"][$name] = array(
      "filename" => pathinfo($filepath, PATHINFO_FILENAME),
      "content-type" => mime_content_type($filepath),
      "data" => base64_encode(file_get_contents($filepath))
    );
  }

  public function end() {
    echo json_encode($this->result);
  }
}

