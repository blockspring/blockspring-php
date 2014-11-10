<?php

class Blockspring {
  public static function run($block, $data = array(), $api_key = null) {
    $api_key = $api_key ? $api_key : getenv('BLOCKSPRING_API_KEY');

    // Data must be given as an array, or array of arrays (so it can be json_encoded).
    if (is_array($data)) {
      $json_data = json_encode($data);
    } else {
      return "Error - the data provided was not an array or nested array: " . print_r($data, true);
    }

    $api_key_string = $api_key ? "api_key=" . $api_key : '';

    $block_parts = explode("/", $block);
    $block = end($block_parts);

    $blockspring_url = getenv('BLOCKSPRING_URL') ? getenv('BLOCKSPRING_URL') : 'https://sender.blockspring.com';

    $url = "{$blockspring_url}/api_v2/blocks/{$block}?{$api_key_string}";
    $options = array(
      'http' => array(
        'header'  => "Content-type: application/json",
        'method'  => 'POST',
        'content' => $json_data,
      ),
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    try {
      $body = json_decode($result);
    } catch (Exception $e){
      $body = $result;
    }

    return $body;
  }

  public static function define($my_function = null){
    $request = new BlockspringRequest();
    $response = new BlockspringResponse();

    print_r($my_function($request, $response));
  }
}

class BlockspringRequest {
  public $params = array();
  public $_errors = array();

  public function __construct(){
    $this->setParamsFromSTDIN();
    $this->setParamsFromArgs();
  }

  private function setParamsFromSTDIN() {
    // Check if something coming into STDIN.
    if (!posix_isatty(STDIN)) {
      $stdin = '';
      while (false !== ($line = fgets(STDIN))) {
        $stdin .= $line;
      }

      // Try to parse inputs as JSON.
      $stdin_params = json_decode($stdin, true);

      if (!$stdin_params) {
        trigger_error("STDIN was not valid JSON.", E_USER_ERROR);
      }

      // If inputs json, check if they're an array.
      if (!is_array($stdin_params)) {
        trigger_error("STDIN was valid JSON, but was not key value pairs.", E_USER_ERROR);
      }

      // Check if following blockspring spec
      if (isset($stdin_params["_blockspring_spec"]) && $stdin_params["_blockspring_spec"]) {
        // We're following spec so lets remove _blockspring_spec, print errors to stderr, and parse files.
        foreach($stdin_params as $key => $val) {
          if ($key == "_blockspring_spec") {
            // Remove _blockspring_spec flag from params.
            unset($stdin_params[$key]);
          } elseif ($key == "_errors" && is_array($stdin_params["_errors"])) {
            // Add errors to request object.

            foreach ($stdin_params["_errors"] as $error) {
              // Make sure the error has a title.
              if (is_array($error) && $error["title"]) {
                $this->addError($error);
              }
            }
          } elseif (is_array($stdin_params[$key]) && $stdin_params[$key]["filename"]) {

            if ($stdin_params[$key]["data"] || $stdin_params[$key]["url"]) {
              // Create temp file
              $prefix = $stdin_params[$key]["filename"] . "-";
              $tmp_file_name = tempnam(sys_get_temp_dir(), $prefix);
              $this->params[$key] = $tmp_file_name;
              $handle = fopen($tmp_file_name, "w");

              // Check if we have raw data
              if ($stdin_params[$key]["data"]) {
                // Try to decode base64, if not set naively.
                try {
                  $file_contents = base64_decode($stdin_params[$key]["data"]);
                } catch (Exception $e) {
                  $file_contents = $stdin_params[$key]["data"];
                }
              } elseif ($stdin_params[$key]["url"]) {
                // Download file and save to tmp file.
                $opts = array(
                  'http' => array(
                    'method' => "GET"
                  )
                );

                $context = stream_context_create($opts);
                $file_contents = file_get_contents($stdin_params[$key]["url"], false, $context);
              }

              // Write to tmp file
              fwrite($handle, $file_contents);
              fclose($handle);
            } else {
              // Set naively since no data or url given.
              $this->params[$key] = $stdin_params[$key];
            }
          } else {
            // Handle everything else
            $this->params[$key] = $stdin_params[$key];
          }
        }
      } else {
        // Not following spec, naively set params.
        $this->params = $stdin_params;
      }
    }
  }

  private function setParamsFromArgs() {
    global $argv;

    $sys_args = array();

    for ($i = 1; $i < count($argv); $i++) {
      if (preg_match('/([^=]*)\=(.*)/', $argv[$i], $match)) {
        $key = (substr($match[1], 0, 2) === "--") ? substr($match[1], 2) : $match[1];
        $sys_args[$key] = $match[2];
      }
    }

    foreach ($sys_args as $key => $val) {
      $this->params[$key] = $val;
    }
  }

  public function getErrors(){
    return $this->_errors;
  }

  public function addError($error){
    array_push($this->_errors, $error);
  }
}

class BlockspringResponse {
  public $result = array(
    "_blockspring_spec" => true,
    "_errors" => array()
  );

  public function addOutput($name, $value) {
    $this->result[$name] = $value;
  }

  public function addFileOutput($name, $filepath) {
    // hardcode csv mimetype
    if (pathinfo($filepath, PATHINFO_EXTENSION) == "csv") {
      $mime = "text/csv";
    } else {
      $mime = mime_content_type($filepath);
    }
    $this->result[$name] = array(
      "filename" => pathinfo($filepath, PATHINFO_FILENAME),
      "content-type" => $mime,
      "data" => base64_encode(file_get_contents($filepath))
    );
  }

  public function addErrorOutput($title, $message = null) {
    array_push($this->result["_errors"], array("title" => $title, "message" => $message));
  }

  public function end() {
    echo json_encode($this->result);
  }
}

