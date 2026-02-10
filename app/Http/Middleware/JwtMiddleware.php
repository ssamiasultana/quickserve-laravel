<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\InvalidClaimException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            
        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token expired'
            ], 401);
            
        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalid: ' . $e->getMessage()
            ], 401);
            
        } catch (InvalidClaimException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token claim: ' . $e->getMessage() . '. Please login again to get a new token.'
            ], 401);
            
        } catch (UnauthorizedHttpException $e) {
            // Handle wrapped exceptions from BaseMiddleware
            $message = $e->getMessage();
            $previous = $e->getPrevious();
            
            // Check if the underlying exception is an InvalidClaimException
            if ($previous instanceof InvalidClaimException || str_contains($message, 'Invalid value provided for claim')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token claim: ' . $message . '. Please login again to get a new token.'
                ], 401);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $message
            ], 401);
            
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token error: ' . $e->getMessage()
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication error: ' . $e->getMessage()
            ], 401);
        }
        return $next($request);
    }
}
