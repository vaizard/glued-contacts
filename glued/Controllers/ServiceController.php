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

    public function contacts_cu1(Request $request, Response $response, array $args = []): Response {
        $headers = '';
        foreach ($request->getHeaders() as $name => $values) {
            $headers .= $name . ": " . implode(", ", $values);
        }
        $r = [
            'qp' => $request->getQueryParams(),
            'pb' => $request->getParsedBody(),
            'fi' => $request->getUploadedFiles(),
            'hd' => $headers
        ];
        return $response->withJson($r);
    }

    public function contacts_d1(Request $request, Response $response, array $args = []): Response {
        $headers = '';
        foreach ($request->getHeaders() as $name => $values) {
            $headers .= $name . ": " . implode(", ", $values);
        }
        $r = [
            'qp' => $request->getQueryParams(),
            'pb' => $request->getParsedBody(),
            'fi' => $request->getUploadedFiles(),
            'hd' => $headers
        ];
        return $response->withJson($r);
    }

    public function contacts_r1(Request $request, Response $response, array $args = []): Response {
        $qp = [];
        $rp = $request->getQueryParams();
        if (!array_key_exists('q', $rp)) { throw new \Exception('Query parameter missing.', 400); };
        if ($rp['q'] == '') { throw new \Exception('Query parameter empty.', 400); };
        $qs = '
            SELECT
                bin_to_uuid(fts.c_sub,1) as uuid,
                GROUP_CONCAT(fts.c_fulltext SEPARATOR " ") AS ftss,
                any_value(obj.data) as data
            FROM `t_contacts_atoms` as fts
                     LEFT JOIN (
                SELECT
                    any_value(c_sub) as c_sub,
                    json_objectagg(c_scheme,data) as data
                FROM (
                         SELECT
                             c_sub AS c_sub,
                             c_scheme,
                             json_arrayagg(c_data) AS data
                         FROM `t_contacts_atoms`
                         GROUP BY c_sub,c_scheme
                     ) AS data
                GROUP BY c_sub
            ) AS obj
            ON obj.c_sub = fts.c_sub
            GROUP BY fts.c_sub
        ';

        $qs = (new \Glued\Lib\QueryBuilder())->select((string) $qs);

        $first = 'ftss LIKE ';
        foreach (explode(" ", $rp['q']) as $k) {
            $qs->having($first.'?');
            $qp[] = '%'.$k.'%';
            $first = "";
        }
        $qs = 'SELECT JSON_OBJECTAGG(uuid,data) as data from (' .$qs .') x';
        $this->logger->debug("contacts" , [ $qs , $qp ] );
        $res = $this->db->rawQuery($qs, $qp)[0]['data'];
        $obj = new \JsonPath\JsonObject($res);
        if (!(array_key_exists('full', $rp) and ($rp['full'] == 1))) {
            $obj->remove('$[*][*][*]', '_v');
            $obj->remove('$[*][*][*]', '_s');
            $obj->remove('$[*][*][*]', '_sub');
            $obj->remove('$[*][*][*]', '_iat');
            $obj->remove('$[*][*][*]', '_iss');
            $obj->remove('$[*]', 'uuid');
        }
        $body = $response->getBody();
        $body->write((string)$obj);
        return $response->withBody($body)->withStatus(200)->withHeader('Content-Type', 'application/json');
    }


    /**
     * Returns a health status response.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function health(Request $request, Response $response, array $args = []): Response {
        $params = $request->getQueryParams();
        $data = [
                'timestamp' => microtime(),
                'status' => 'OK',
                'params' => $params,
                'service' => basename(__ROOT__),
                'provided-for' => $_SERVER['X-GLUED-AUTH-UUID'] ?? 'anon',
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

