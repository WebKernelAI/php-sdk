<?php

namespace WebKernelAI\SDK\Exceptions;

use Exception;

class WebKernelException extends Exception {}
class ConfigurationException extends WebKernelException {}
class AuthenticationException extends WebKernelException {}
class SignatureException extends WebKernelException {}
class WafBlockException extends WebKernelException {}
class ApiException extends WebKernelException {}
class CacheException extends WebKernelException {}
