<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
Route::get('/assets/{file}', function (string $file) {
    $extension=pathinfo($file,PATHINFO_EXTENSION);
    $types=['js'=>'text/javascript','css'=>'text/css','woff2'=>'font/woff2','png'=>'image/png','svg'=>'image/svg+xml'];
    abort_unless(isset($types[$extension]),404);
    $path=base_path('dist/assets/'.$file);abort_unless(is_file($path),404);
    return response()->file($path,['Content-Type'=>$types[$extension],'Cache-Control'=>'public, max-age=31536000, immutable','X-Content-Type-Options'=>'nosniff']);
})->where('file','[A-Za-z0-9_-]+\.[A-Za-z0-9]+');
Route::fallback(function(Request $request) {
    abort_if($request->is('api','api/*') || str_contains($request->path(),'.'),404);
    $index=base_path('dist/index.html');abort_unless(is_file($index),503,'Compilez l’interface avec npm run build.');
    return response()->file($index,['Content-Type'=>'text/html; charset=utf-8','Cache-Control'=>'no-cache','X-Content-Type-Options'=>'nosniff','Referrer-Policy'=>'strict-origin-when-cross-origin','Content-Security-Policy'=>"default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' https://media.api-sports.io https://highlightly.net data:; connect-src 'self'; base-uri 'self'; frame-ancestors 'none'"]);
});
