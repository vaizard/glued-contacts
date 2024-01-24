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
use Ramsey\Uuid\Uuid;


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

    private function contacts_patch($json, $objUUID = null)
    {
        if (is_null($objUUID)) { $objUUID = Uuid::uuid4()->toString(); }
        $q = " 
        INSERT INTO `t_contacts_objects` 
            (`c_uuid`, `c_data`) VALUES (uuid_to_bin(?, 1), ?) 
        ON DUPLICATE KEY UPDATE
            c_data = JSON_MERGE_PATCH(c_data, values(c_data)),
            c_rev = c_rev + 1
        ";
        return $this->mysqli->execute_query($q, [ $objUUID, $json ]);
    }

    public function contacts_p1(Request $request, Response $response, array $args = []): Response {
        $data = $request->getParsedBody();
        echo $data;
        echo ($args['uuid'] ?? null);
        return $response;
        return $response->withBody($body)->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    public function contacts_r1(Request $request, Response $response, array $args = []): Response {
        $wq = "";
        $data = [];
        $object = $args['uuid'] ?? false;
        if ($object) { $wq .= " AND c_uuid = uuid_to_bin(?, true)"; $data[] = $object; }
        foreach ($request->getQueryParams() as $qp) {
            if (is_array($qp)) {
                foreach ($qp as $item) {
                    $st[] = "c_ft LIKE CONCAT('%',?,'%')";
                    $data[] = $item;
                }
            } else {
                $st[] = "c_ft LIKE CONCAT('%',?,'%')";
                $data[] = $qp;
            }
        }
        if (count($request->getQueryParams())>0) {
            $wq .= " AND " . implode(" OR ", $st);
        }

        $q = "SELECT JSON_ARRAYAGG(JSON_INSERT(c_data, '$.uuid', bin_to_uuid(c_uuid, true))) AS data 
              FROM t_contacts_objects
              WHERE 1=1 $wq";

        $result = $this->mysqli->execute_query($q, $data);
        $obj = null;
        foreach ($result as $row) { $data = json_decode($row['data'] ?? '{}'); break; }
        $data = [
            'timestamp' => microtime(),
            'status' => 'OK',
            'data' => $data,
            'service' => basename(__ROOT__),
        ];
        return $response->withJson($data);
    }

    public function import_r1(Request $request, Response $response, array $args = []): Response {
        $action = $args['act'] ?? null;
        $fid = $args['key'] ?? null;

        $sql = "
        INSERT INTO `t_contacts_objects` (`c_uuid`, `c_data`, `c_ft`)
        SELECT
          uuid_to_bin(uuid(), true) AS `c_uuid`,
          c_data AS `c_data`,
          GROUP_CONCAT(extracted_val.val SEPARATOR ' ') AS c_ft
        FROM
          `t_if__objects`,
           JSON_TABLE(
             c_data,
              '$**.val' COLUMNS (
                val VARCHAR(255) PATH '$'
              )
           ) AS extracted_val
        WHERE c_action = uuid_to_bin(?, true) AND c_fid = ?
        GROUP BY c_data 
        ON DUPLICATE KEY UPDATE
          `c_ft` = VALUES(`c_ft`);
        ";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ss", $action, $fid);
        $stmt->execute();
        $stmt->close();

        $q = "SELECT  bin_to_uuid(co.c_uuid, true) as uuid FROM `t_if__objects` io
              left join t_contacts_objects co ON co.c_hash = io.c_hash
              WHERE io.c_action = uuid_to_bin(?, true) AND io.c_fid = ?
        ";
        $result = $this->mysqli->execute_query($q,[ $action, $fid]);
        $obj = null;
        foreach ($result as $row) { $obj = $row; break; }
        if (is_null($obj)) { throw new \Exception('Contact not imported.', 500); }

        $data = [
            'timestamp' => microtime(),
            'status' => 'OK',
            'service' => basename(__ROOT__),
            'data' => $obj
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
        from `t_contacts_rels`
        EOT;
        $qs = (new \Glued\Lib\QueryBuilder())->select($qs);
        $wm = [ 'tags' => 'json_contains(`c_data`->>"$.tags", ?)' ];
        $this->utils->mysqlJsonQueryFromRequest($rp, $qs, $qp, $wm);
        $res = $this->db->rawQuery($qs, $qp);
        return $this->utils->mysqlJsonResponse($response, $res);
    }

}

