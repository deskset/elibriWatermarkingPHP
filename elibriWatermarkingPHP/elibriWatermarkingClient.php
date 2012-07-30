<?php

//! @defgroup exceptions Wyjątki
//! @{
//! Lista wyjątków, które mogą zostać wygenerowane podczas połączenia z serwerem.
//! @}

//! @brief Wyjątek używany w przypadku wystąpienia błędu połączenia z serwerem
//! @ingroup exceptions
class ElibriAPIConnectionException extends Exception {

  //! konstruktor wyjątku w przypadku błędu zwróconego przez curl-a
  function __construct($msg, $errno) {
    parent::__construct($msg, $errno);
  }
}

//! @brief Wyjątek - Podane zostały błędne parametry
//! @ingroup exceptions
class ElibriParametersError extends Exception {
  
  function __construct() {
    parent::__construct("Bad Parameters", 400);
  
  }
}


//! @brief Wyjątek - brak autoryzacji
//! @ingroup exceptions
class ElibriInvalidAuthException extends Exception {

  function __construct() {
    parent::__construct("Unauthorized", 401);
  
  }
}

//! @brief Wyjątek po stronie serwera (Internal server error)
//! @ingroup exceptions
class ElibriServerErrorException extends Exception {

  function __construct() {
    parent::__construct("Server Error", 500);
  }
}


//! @brief Wyjątek po stronie serwera (Internal server error)
//! @ingroup exceptions
class ElibriForbiddenException extends Exception {

  function __construct() {
    parent::__construct("Forbidden", 403);
  }
}



//! @brief Wyjątek - Nieprawidłowy login lub hasło
//! @ingroup exceptions
class ElibriWrongFormatsException extends Exception {
  
  function __construct() {
    parent::__construct("Błędnie podany format", 1000);
  }
}

//! @brief Wyjątek - Nieprawidłowy login lub hasło
//! @ingroup exceptions
class ElibriNotFoundException extends Exception {

  function __construct() {
    parent::__construct("Invalid Login or Password", 1000);
  }
}

//! @brief Wyjątek - Nieprawidłowy login lub hasło
//! @ingroup exceptions
class ElibriUnknownException extends Exception {

  function __construct() {
    parent::__construct("Invalid Login or Password", 1000);
  }
}


//! @brief ElibriAPI abstrahuje wykorzystanie API udostępniane przez eLibri
class ElibriWatermarkingClient {

  private $host = 'https://www.elibri.com.pl/watermarking';
  private $token;
  private $secret;
  
  //! Kontruktor obiektu API
  function __construct($token, $secret, $host=NULL) {
  
    $this->token = $token;
    $this->secret = $secret;
    if (isset($_host)) $this->host = $host;
  }

  //! Zlecaj watermarkowanie. Dopóki nie zostanie metoda deliver, plik nie zostanie udostępniony
  function watermark($ident, $formats, $visible_watermark, $title_postfix) {
    if (preg_match('/^[0-9]+$/', $ident)) { 
      $ident_type = 'isbn';
    } else {
      $ident_type = 'record_reference';
    }

    if (!preg_match('/^(epub|mobi|,)+$/', $formats)) {
      throw new ElibriWrongFormatsException();
    }

    $uri = $this->host . '/watermark';
 
    $data = array($ident_type => $ident, 'formats' => $formats, 'visible_watermark' => $visible_watermark,
                  'title_postfix' => $title_postfix);

    return $this->send_request($uri, $data);
  }

  function deliver($trans_id) {
    $uri = $this->host . '/deliver';
    $data = array('trans_id' => $trans_id);
    return $this->send_request($uri, $data);
  }

  private function send_request($uri, $data) {
    $stamp = time(); 
    $sig = rawurlencode(base64_encode(hash_hmac("sha1", $this->secret, $stamp, true)));
    $data['stamp'] = $stamp;
    $data['sig'] = $sig;
    $data['token'] = $this->token;

    $ch = curl_init($uri);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    $curlResult = curl_exec($ch);
    return $this->validate_response($curlResult, $ch);
  }

  private function validate_response($curlResult, $ch) {
    if ($curlResult === FALSE) {
      throw new ElibriAPIConnectionException(curl_error($ch), curl_errno($ch));
    }

    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response_code == 404) {
      throw new ElibriNotFoundException();
    } else if ($response_code == 400) {
      throw new ElibriParametersError();
    } else if ($response_code == 403) {
      throw new ElibriForbiddenException();
    } else if ($response_code == 500) {
      throw new ElibriServerErrorException();
    } else if ($response_code == 401) {
      throw new ElibriInvalidAuthException();
    } else if (($response_code != 200) && ($response_code != 412)) {
      throw new ElibriUnknownException();
    }
    return $curlResult;
  }  

}

?>
