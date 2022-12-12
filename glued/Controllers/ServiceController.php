<?php

declare(strict_types=1);

namespace Glued\Controllers;

use Firebase\JWT\ExpiredException;
use Glued\Lib\Exceptions\InternalException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Classes\Exceptions\AuthTokenException;
use Glued\Classes\Exceptions\AuthJwtException;
use Glued\Classes\Exceptions\AuthOidcException;
use Glued\Classes\Exceptions\DbException;
use Glued\Classes\Exceptions\TransformException;
use Symfony\Component\Config\Definition\Exception\Exception;


class ServiceController extends AbstractController
{

    /**
     * Returns an exception.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function stub(Request $request, Response $response, array $args = []): Response {
        throw new \Exception('Stub method served where it shouldn\'t. Proxy misconfiguration?');
    }


    public function _r1(Request $request, Response $response, array $args = []): Response {
        return $this->BuildResponse($response, $res, meta: $meta, fmt: $fmt);
    }


    /**
     * Returns a health status response.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function health(Request $request, Response $response, array $args = []): Response {
        $srv = $this->settings['sqlsrv']['hostname'];
        $cnf = [
            "Database" => $this->settings['sqlsrv']['database'],
            "UID" =>  $this->settings['sqlsrv']['username'],
            "PWD" =>  $this->settings['sqlsrv']['password']
            ];

        $status = "OK";
        $messages = [];
        $conn = sqlsrv_connect($srv,$cnf);
        if ($conn) {
           $status = 'OK';
        } else {
            $messages = sqlsrv_errors();
            $status = 'Degraded';
        }
        $params = $request->getQueryParams();
        $data = [
                'timestamp' => microtime(),
                'status' => $status,
                'params' => $params,
                'service' => basename(__ROOT__),
                'provided-for' => $_SERVER['X-GLUED-AUTH-UUID'] ?? 'anon',
                'hint' => $messages
            ];
        return $response->withJson($data);
    }



    /**
     * Returns a health status response.
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function rels_r1(Request $request, Response $response, array $args = []): Response
    {
        $rp = $request->getQueryParams();
        $qp = null;
        $qs = <<<EOT
        select 
                json_merge_preserve(
                    json_object("uuid", bin_to_uuid( `c_uuid`, true)),
                    `c_data`
                ) as res_rows
        from `t_fare_rels`
        EOT;
        $qs = (new \Glued\Lib\QueryBuilder())->select($qs);
        $wm = [ 'tags' => 'json_contains(`c_data`->>"$.tags", ?)' ];
        $this->utils->mysqlJsonQueryFromRequest($rp, $qs, $qp, $wm);
        $res = $this->db->rawQuery($qs, $qp);
        return $this->utils->mysqlJsonResponse($response, $res);
    }

}
