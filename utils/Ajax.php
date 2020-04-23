<?php
/**
 * Provides functions to asynchronous communication
 * between clients and server via HTTP.
 **/
class Ajax {

    //methods

    /**
     * Encodes object to JSON-Format and sends it
     * to the client.
     * @param StdClass $data
     * @return void
     **/
    public function send(StdClass $data) {
        echo json_encode( (array) $data);
    }

    /**
     * Receives data in JSON-Format
     * and decode it to an object.
     * @param void
     * @return StdClass
     **/
    public function receive(): StdClass {
        $json = json_decode(file_get_contents("php://input"));
        return ($json == NULL) ? new StdClass() : $json;
    }
}
?>