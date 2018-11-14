<?php

namespace DDTrace\Integrations;

use DDTrace\Tags;
use DDTrace\Types;
use OpenTracing\GlobalTracer;

/**
 * Tracing of the Curl library.
 *
 * Currently only wraps curl_exec and adds info to the trace from curl_getinfo
 */
class Curl
{
    public static function load()
    {
        if (!extension_loaded('ddtrace')) {
            trigger_error('The ddtrace extension is required to instrument Memcached', E_USER_WARNING);
            return;
        }
        if (!function_exists('curl_exec')) {
            trigger_error('Curl is not loaded and connot be instrumented', E_USER_WARNING);
        }

        dd_trace('curl_exec', function ($ch) {
            $scope = GlobalTracer::get()->startActiveSpan("curl");
            $span = $scope->getSpan();
            $span->setTag(Tags\SPAN_TYPE, Types\CURL);
            $span->setTag(Tags\SERVICE_NAME, 'curl');

            try {
                $result = curl_exec($ch);

                $info = curl_getinfo($ch);
                $span->setResource($info['url']);
                $span->setTag('curl.http_code', $info['http_code']);
                $span->setTag('curl.header_size', $info['header_size']);
                $span->setTag('curl.request_size', $info['request_size']);

                return $result;
            } catch (\Exception $e) {
                $span->setError($e);
                throw $e;
            } finally {
                $scope->close();
            }
        });
    }
}
