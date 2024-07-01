<?php

declare(strict_types=1);

namespace Glued\Controllers;

use Glued\Lib\Controllers\AbstractService;
use Glued\Lib\Sql;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use JsonPath\JsonObject;


use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class ServiceController extends AbstractService
{

    /*
    public string $schema = '
        {
  "$schema": "http://json-schema.org/draft-2020-12/schema#",
  "type": "object",
  "properties": {
    "name": {
      "type": ["array", "null"],
      "minItems": 1,
      "items": {
        "type": "object",
        "properties": {
          "val": { "type": "string" },
          "kind": { "type": "string", "default": "fn" },
          "props": { "type": "array", "items": { "type": "string" } }
        },
        "required": ["val"]
      }
    },
    "uuid": { "type": "string" },
    "id": {
      "type": "array",
      "minItems": 1,
      "items": {
        "type": "object",
        "properties": {
          "val": { "type": "string" },
          "regid": { "type": "string" },
          "vatid": { "type": "string" },
          "registry": {
            "type": "object",
            "properties": {
              "iat": { "type": "string", "format": "date" },
              "uat": { "type": "string", "format": "date" },
              "file": { "type": "string" }
            },
            "required": []
          },
          "countrycode": { "type": "string" }
        },
        "required": ["val"]
      }
    },
    "address": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "val": { "type": "string" },
          "kind": { "type": "string" },
          "region": { "type": "string" },
          "street": { "type": "string" },
          "suburb": { "type": "string" },
          "district": { "type": "string" },
          "postcode": { "type": "integer" },
          "countrycode": { "type": "string" },
          "municipality": { "type": "string" },
          "streetnumber": { "type": "integer" },
          "conscriptionnumber": { "type": "integer" },
          "building": { "type": "string", "description": "Building name, i.e. `Industra`" },
          "floor": { "type": "string" },
          "unit": { "type": "string", "description": "`Apartment 23` or `Surgery department`" },
          "room": { "type": "string", "description": "Room name - i.e. `Operating room 4`" },
          "name": { "type": "string", "description": "A callsign for the location, i.e. `CH - OR4`" },
          "props": { "type": "array", "items": { "type": "string" } }
        },
        "required": ["val"]
      }
    },
    "domicile": { "type": "string" },
    "registration": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "val": { "type": "string" },
          "date": {
            "type": "object",
            "properties": {
              "iss": { "type": "string", "format": "date", "description": "Issued at" },
              "eff": { "type": "string", "format": "date", "description": "Effective at" },
              "upd": { "type": "string", "format": "date", "description": "(Last) updated at" },
              "vrf": { "type": "string", "format": "date", "description": "(Last) verified at" },
              "exp": { "type": "string", "format": "date", "description": "Expires at" }
            },
            "required": []
          },
          "kind": { "type": "string" },
          "issuer": { "type": "string" },
          "countrycode": { "type": "string" },
          "stor": { "type": "string" }
        },
        "required": ["val"]
      }
    },
    "email": {
      "type": ["array", "null"],
      "minItems": 1,
      "items": {
        "type": "object",
        "properties": {
          "val": { "type": "string" },
          "label": { "type": "string" }
        },
        "required": ["val"]
      }
    },
    "phone": {
      "type": ["array", "null"],
      "minItems": 1,
      "items": {
        "type": "object",
        "properties": {
          "val": { "type": "string" },
          "label": { "type": "string" }
        },
        "required": ["val"]
      }
    },
    "natid": {
      "type": ["array", "null"],
      "minItems": 1,
      "items": {
        "type": "object",
        "properties": {
          "val": { "type": "string" },
          "label": { "type": "string" },
          "countrycode": { "type": "string" }
        },
        "required": ["val"]
      }
    },
    "event": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "kind": { "type": "string", "enum": ["dob", "dod", "anniversary", "fiscalyear", "other"], "default": "other" },
          "dtstart": { "type": "string", "format": "date" },
          "dtend": { "type": "string", "format": "date" },
          "duration": { "type": "string", "format": "date" },
          "summary": { "type": "string" },
          "rrule": { "type": "string" },
          "rdate": { "type": "string" },
          "exdate": { "type": "string" },
          "cal": { "type": "string", "description": "Glued calendar uri" },
          "val": { "type": "string" }
        },
        "required": ["kind", "val"]
      }
    },
    "pay": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "iban": { "type": "string" },
          "accountnr": { "type": "string" },
          "routingnr": { "type": "string" },
          "bic": { "type": "string" },
          "currency": { "type": "string" },
          "bankname": { "type": "string", "description": "Bank name" },
          "bankaddr": { "type": "string", "description": "Bank address" },
          "label": { "type": "string", "description": "User defined label such as `main CZK account`" },
          "uri": { "type": "array", "description": "Uris in the RFC 8905 payto scheme, or special purpose uris such as revolut.me/id" },
          "val": { "type": "string" }
        },
        "required": ["val"]
      }
    },
    "uri": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "kind": { "type": "string" },
          "val": { "type": "string" }
        },
        "required": ["kind", "val"]
      }
    }
  },
  "anyOf": [
    { "required": ["name"] },
    { "required": ["email"] },
    { "required": ["phone"] }
  ]
}
        ';*/
    public string $schema = '
        {
          "$schema": "http://json-schema.org/draft-2020-12/schema#",
          "type": "object",
          "properties": {
            "id": {
              "type": "array",
              "items": {
                "type": "object",
                "properties": {
                  "val": { "type": "string" },
                  "regid": { "type": "string" },
                  "vatid": { "type": "string" },
                  "registry": {
                    "type": "object",
                    "properties": {
                      "iat": { "type": "string", "format": "date" },
                      "uat": { "type": "string", "format": "date" },
                      "file": { "type": "string" }
                    },
                    "required": []
                  },
                  "countrycode": { "type": "string" }
                },
                "required": ["val"]
              }
            },
            "name": {
              "type": "array",
              "minItems": 1,
              "items": {
                "type": "object",
                "properties": {
                  "val": { "type": "string" },
                  "props": { "type": "array", "items": { "type": "string" } }
                },
                "required": ["val"]
              }
            },
            "uuid": { "type": "string" },
            "props": { "type": "array", "items": { "type": "string" } },
            "address": {
              "type": "array",
              "items": {
                "type": "object",
                "properties": {
                  "val": { "type": "string" },
                  "props": { "type": "array", "items": { "type": "string" } },
                  "region": { "type": "string" },
                  "street": { "type": "string" },
                  "suburb": { "type": "string" },
                  "district": { "type": "string" },
                  "postcode": { "type": "integer" },
                  "countrycode": { "type": "string" },
                  "municipality": { "type": "string" },
                  "streetnumber": { "type": "integer" },
                  "conscriptionnumber": { "type": "integer" }
                }
              }
            },
            "created_at": { "type": "string", "format": "date-time" },
            "updated_at": { "type": "string", "format": "date-time" }
          }
        }
    ';

        protected $validator;
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->validator = new Validator();
        $resolver = $this->validator->loader()->resolver();
        $resolver->registerRaw($this->schema, 'https://glued/contacts/object.json');
    }

    public function contacts_d1(Request $request, Response $response, array $args = []): Response
    {
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
        if (is_null($objUUID) or !$objUUID) {
            $objUUID = Uuid::uuid4()->toString();
        }

        $j = new JsonObject($json, true);
        $ft = implode(" ", $j->{'$..val'});
        $q = " 
        INSERT INTO `t_contacts_objects` 
            (`c_uuid`, `c_data`, `c_ft`) VALUES (uuid_to_bin(?, 1), ?, ?) 
        ON DUPLICATE KEY UPDATE
            c_data = JSON_MERGE_PATCH(c_data, values(c_data)),
            `c_ft` = VALUES(`c_ft`);
        ";
        return $this->mysqli->execute_query($q, [$objUUID, $json, $ft]);
    }

    public function contacts_p1(Request $request, Response $response, array $args = []): Response
    {
        if (($request->getHeader('Content-Type')[0] ?? '') != 'application/json') { throw new \Exception('Content-Type header missing or not set to `application/json`.', 400); };
        $uuid = $args['uuid'] ?? false;
        $object = json_decode(json_encode($request->getParsedBody()));

        $validation = $this->validator->validate($object, 'https://glued/contacts/object.json');
        if ($validation->isValid()) {
            $res = [
                'valid' => true,
                'data' => $object
            ];
        }

        if ($validation->hasError()) {
            $formatter = new ErrorFormatter();
            $error = $validation->error();
            $res = $formatter->formatOutput($error, "basic");
            return $response->withJson($res);
        }
        $this->contacts_patch(json_encode($object), $uuid);
        return $response->withJson($res);
    }

    public function postCards(Request $request, Response $response, array $args = []): Response
    {
        if (($request->getHeader('Content-Type')[0] ?? '') != 'application/json') { throw new \Exception('Content-Type header missing or not set to `application/json`.', 400); };
        $uuid = $args['uuid'] ?? false;
        $doc = json_decode(json_encode(current($request->getParsedBody())));
        $validation = $this->validator->validate((object) $doc, 'https://glued/contacts/object.json');
        if ($validation->isValid()) {
            $res = [
                'valid' => true,
                'data' => $doc
            ];
        }

        if ($validation->hasError()) {
            $formatter = new ErrorFormatter();
            $error = $validation->error();
            $res = $formatter->formatOutput($error, "basic");
            return $response->withJson($res);
        }

        $db = new Sql($this->pg, 'contacts_cards');
        $res = $db->create($doc, true, true);
        return $response->withJson($res);
    }

    public function getCard(Request $request, Response $response, array $args = []): Response
    {
        if (empty($args['uuid'])) { throw new \Exception('No uuid specified', 404); }
        $db = new Sql($this->pg, 'contacts_cards');
        $data = $db->get($args['uuid']);
        return $response->withJson($data);
    }

    public function getCards(Request $request, Response $response, array $args = []): Response
    {
        $db = new Sql($this->pg, 'contacts_cards');
        $q = $request->getQueryParams()['q'] ?? false;
        if ($q) { $qp['ft'] = "%$q%"; }
        $filters = ['ft'];
        foreach ($filters as $filter) {
            if (!empty($qp[$filter])) { $db->where($filter, 'ILIKE', $qp[$filter]); }
        }
        $data = $db->getAll();
        return $response->withJson($data);
    }

    public function importFromCache(Request $request, Response $response, array $args = []): Response
    {
        $action = $args['act'] ?? null;
        $fid = $args['key'] ?? null;

        $fetchSql = <<< EOL
        SELECT res_payload || jsonb_build_object('uuid', gen_random_uuid()) AS doc
        FROM if__actions_valid_response_cache
        WHERE action_uuid = :actionUUID AND fid = :fid
        EOL;

        $fetchStmt = $this->pg->prepare($fetchSql);
        $fetchStmt->execute([':actionUUID' => $action, ':fid' => $fid]);
        $result = $fetchStmt->fetch($this->pg::FETCH_ASSOC);
        if (!$result) { throw new \Exception('No data found in cache.', 500); }

        $doc = json_decode($result['doc'], true);

        // Extract all "val" keys and concatenate them
        $vals = [];
        array_walk_recursive($doc, function ($value, $key) use (&$vals) {
            if ($key === 'val') { $vals[] = $value; }
        });
        $ft = implode(' ', $vals);

        // Insert into contacts_cards
        $insertSql = <<< EOL
        INSERT INTO contacts_cards (doc, ft)
        VALUES (:doc::jsonb, :ft)
        ON CONFLICT (nonce) DO UPDATE SET
        ft = EXCLUDED.ft;
        EOL;
        $insertStmt = $this->pg->prepare($insertSql);
        $insertStmt->execute([':doc' => json_encode($doc), ':ft' => $ft]);

        $q = "SELECT uuid FROM contacts_cards cc
          LEFT JOIN if__actions_valid_response_cache avrc ON cc.nonce = decode(md5(avrc.res_payload::text), 'hex')
          WHERE avrc.action_uuid = :actionUUID AND avrc.fid = :fid";
        $stmt = $this->pg->prepare($q);
        $stmt->execute([':actionUUID' => $action, ':fid' => $fid]);
        $obj = $stmt->fetch($this->pg::FETCH_ASSOC);

        if (!$obj) {
            throw new \Exception('Contact not imported.', 500);
        }

        if (array_key_exists('uuid', $obj)) {
            $uri = "{$this->settings['glued']['baseuri']}{$this->settings['routes']['be_contacts_cards']['pattern']}/{$obj['uuid']}";
        }

        return $response->withRedirect($uri, 302);
    }

    /**
     * Returns a health status response.
     * @param Request $request
     * @param Response $response
     * @param array $args
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
        $wm = ['tags' => 'json_contains(`c_data`->>"$.tags", ?)'];
        $this->utils->mysqlJsonQueryFromRequest($rp, $qs, $qp, $wm);
        $res = $this->db->rawQuery($qs, $qp);
        return $this->utils->mysqlJsonResponse($response, $res);
    }
}
