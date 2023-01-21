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
              c_sub
              ,json_objectagg(c_scheme, c_data) as data
            FROM `t_contacts_atoms` 
            GROUP BY c_sub
            ) as obj
            on obj.c_sub = fts.c_sub
            GROUP BY fts.c_sub
        ';
        $qs = (new \Glued\Lib\QueryBuilder())->select((string) $qs);

        $first = 'ftss LIKE ';
        foreach (explode(" ", $rp['q']) as $k) {
            $qs->having($first.'?');
            $qp[] = '%'.$k.'%';
            $first = "";
        }
        $qs = 'SELECT JSON_ARRAYAGG(data) as data from (' .$qs .') x';
        $res = $this->db->rawQuery($qs, $qp)[0]['data'];
        $body = $response->getBody();
        $body->write($res);
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
