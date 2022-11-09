<?php

namespace App\Helpers\Common;

use Auth;
use App\User;
use FFMpeg as FFMpeg;
use Illuminate\Http\File;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

class Functions
{
    /**
     * @param int $user_id User-id
     * 
     * @return string
     */

    public static function digitsFormate($digits)
    {
        $formatedDigits = 0;
        if ($digits >= 1000000000) {
            $formatedDigits = number_format($digits / 1000000000, 2) . ' G';
        } elseif ($digits >= 1000000) {
            $formatedDigits = number_format($digits / 1000000, 2) . ' M';
        } elseif ($digits >= 1000) {
            $formatedDigits = number_format($digits / 1000, 2) . ' K';
        } else {
            $formatedDigits = $digits;
        }
        return $formatedDigits;
    }

    public static function time_elapsed_string($datetime, $full = false)
    {
        $now = new \DateTime;
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'y',
            'm' => 'm',
            'w' => 'w',
            'd' => 'd',
            'h' => 'h',
            'i' => 'm',
            's' => 's',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . $v;
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) : 'just now';
    }

    public static function getFolderName($folder, $size = '')
    {

        $path = $folder;

        if ($size != '') {
            $path .= '/' . $size;
        }

        $folderExists = Storage::exists($path);

        if (!$folderExists) {
            Storage::makeDirectory($path);
        }

        return $path;
    }



    public static function createThumb($imagePath, $req_width, $req_height, $path, $filename)
    {

        $img = Image::make($imagePath);

        // Creating thumbnail without crop of specific size
        $img->resize($req_width, $req_height, function ($constraint) {
            $constraint->aspectRatio();
        });

        Storage::put($path . '/' . $filename, $img->stream()->__toString(), 'public');
        return true;
    }

    public static function createThumbWidth($imagePath, $req_width, $path, $filename)
    {

        $img = Image::make($imagePath);

        $img->resize($req_width, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        Storage::put($path . '/' . $filename, $img->stream()->__toString(), 'public');
        return true;
    }

    public static function createThumbHeight($imagePath, $req_height, $path, $filename)
    {

        $img = Image::make($imagePath);

        $img->resize(null, $req_height, function ($constraint) {
            $constraint->aspectRatio();
        });

        Storage::put($path . '/' . $filename, $img->stream()->__toString(), 'public');
        return true;
    }

    public static function saveImage($file, $folder)
    {
        $path = self::getFolderName($folder);
        $filenametostore = $file->store($path);
        Storage::setVisibility($filenametostore, 'public');
        $fileArray = explode('/', $filenametostore);
        $fileName = array_pop($fileArray);
        return $fileName;
    }

    public static function saveImageAs($file, $folder, $fileName)
    {
        $path = self::getFolderName($folder);
        $file->storeAs($folder, $fileName);
        Storage::setVisibility($folder . '/' . $fileName, 'public');
        return true;
    }

    public static function getImageName($color_filter, $cat_id, $product_id, $image_no, $extension)
    {
        $image_name = self::nameToAlias($color_filter) . '-';
        foreach ($cat_id as $value) {
            $category = DB::table('categories')
                ->select(DB::raw("cat_name,alias"))
                ->where('cat_id', $value)
                ->first();
            if ($category->alias) {
                $image_name .= $category->alias . '-';
            } else {
                $image_name .= self::nameToAlias($category->cat_name) . '-';
            }
        }
        $image_name .= $product_id . '-' . $image_no . '.' . $extension;
        return $image_name;
    }

    public static function nameToAlias($name)
    {
        return str_replace(' ', '-', strtolower($name));
    }

    public static function alaisToName($alias)
    {
        return str_replace('-', ' ', strtoupper($name));
    }

    public static function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        // trim
        $text = trim($text, '-');
        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);
        // lowercase
        $text = strtolower($text);
        if (empty($text)) {
            return 'n-a';
        }
        return $text;
    }

    public static function getName()
    {
        $admin = Auth::guard('admin')->user();
        echo ucfirst($admin->name);
    }

    public static function getCategories()
    {

        $categories = DB::table('categories')
            ->select(DB::raw("*"))
            ->where('visible', 1)
            ->orderBy('rank', 'ASC')
            ->get();

        return $categories;
    }
    public static function validate_token($user_id, $app_token)
    {
        $res = DB::table('users')
            ->select(DB::raw("count(*) as user_count"))
            ->where('user_id', $user_id)
            ->where('app_token', $app_token)
            //->where('user_type',$type)
            ->first();

        if ($res->user_count == 0) {
            return 0;
        } else {
            return 1;
        }
    }


    public function date_time($time)
    {
        $timestamp = strtotime($time);
        $date = date('d/m/Y', $timestamp);
        $time = date('h:i A', $timestamp);

        if ($date == date('d/m/Y')) {
            $date = $time;
            return $date;
        } else if ($date == date('d/m/Y', strtotime(' -1 day'))) {
            $date = 'Yesterday at ' . $time;
            return $date;
        } else {
            return date('M d,Y ', $timestamp) . " at " . date('h:i A', $timestamp);
        }
    }

    public function _password_generate($chars)
    {
        $data = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcefghijklmnopqrstuvwxyz';
        return substr(str_shuffle($data), 0, $chars);
    }

    public function _getUserFolderName($user_id, $folder)
    {
        $path = $folder . '/' . $user_id;
        $folderExists = Storage::disk('local')->exists($path);
        if (!$folderExists) {
            Storage::disk('local')->makeDirectory($path);
        }
        return $path;
    }


    public function _cropImage($imagePath, $req_width, $req_height, $x, $y, $path, $filename)
    {

        $img = Image::make($imagePath);

        $width  = $img->width();
        $height = $img->height();

        $vertical   = (($width < $height) ? true : false);
        $horizontal = (($width > $height) ? true : false);
        $square     = (($width == $height) ? true : false);
        //$vertical = true;

        if ($vertical) {

            $new_height = $req_height;
            $new_width = round(($new_height / $height) * $width);

            //$top = $bottom = ( ( $req_height - $height ) / 2);
            //$newHeight = ($req_height) - ($bottom + $top);

            $img->resize($new_width, $new_height, function ($constraint) {
                $constraint->aspectRatio();
            });

            $img->crop($new_width, $new_width, $x, $y);
            $img->fit(500, 500, function ($constraint) {
                $constraint->upsize();
            });
            //$img->resizeCanvas($new_width, $new_height, 'center', true, '#ffffff');

        } else {

            $new_height = round(($req_width / $width) * $height);
            $new_width = $req_width;

            $img->resize($new_width, $new_height, function ($constraint) {
                $constraint->aspectRatio();
            });
            $img->fit(500, 500, function ($constraint) {
                $constraint->upsize();
            });

            $img->crop($new_height, $new_height, $x, $y);
            // $img->resizeCanvas($new_width, $new_height, 'center', true, '#ffffff'); 
        }

        Storage::put($path . '/' . $filename, $img->stream()->__toString(), 'public');

        return true;
    }

    public function _createThumb($imagePath, $req_width, $req_height, $path, $filename)
    {

        $img = Image::make($imagePath);

        $width  = $img->width();
        $height = $img->height();

        $vertical   = (($width < $height) ? true : false);
        $horizontal = (($width > $height) ? true : false);
        $square     = (($width == $height) ? true : false);
        $vertical = true;

        if ($vertical) {

            $new_height = $req_height;
            $new_width = round(($new_height / $height) * $width);

            //$top = $bottom = ( ( $req_height - $height ) / 2);
            //$newHeight = ($req_height) - ($bottom + $top);

            $img->resize($new_width, $new_height, function ($constraint) {
                $constraint->aspectRatio();
            });
        } else {

            $new_height = round(($req_width / $width) * $height);
            $new_width = $req_width;

            $img->resize($new_width, $new_height, function ($constraint) {
                $constraint->aspectRatio();
            });
        }

        $img->resizeCanvas($new_width, $new_height, 'center', false, '#ffffff');

        Storage::put($path . '/' . $filename, $img->stream()->__toString(), 'public');

        return true;
    }

    public function _user_check($user_id, $app_token, $type)
    {
        $user = DB::table("users")
            ->select(DB::raw("count(*) as user_count"))
            ->where('user_id', $user_id)
            ->where('app_token', $app_token)
            ->where('user_type', $type)
            ->first();

        if ($user->user_count == 0) {
            return 0;
        } else {
            return 1;
        }
    }

    public function valid_candidate($user_id, $type)
    {
        $user = DB::table("users")
            ->select(DB::raw("count(*) as user_count"))
            ->where('user_id', $user_id)
            ->where('user_type', $type)
            ->where('active', 1)
            ->first();

        if ($user->user_count == 0) {
            return 0;
        } else {
            return 1;
        }
    }
    public static function fSafeNum($str)
    {
        if ($str) {
            $str = trim($str);
            $str = str_replace(" ", "", $str);
            $str = str_replace(",", "", $str);
            if (is_numeric($str)) {
                return doubleval($str);
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }
    public static function fSafeChar($str)
    {
        if ($str) {
            $str = trim($str);
            $str = str_replace("\'", "'", $str);
            $str = str_replace("'", "''", $str);
            return $str;
        } else {
            return "";
        }
    }
    public static function getLogo()
    {
        $settings = DB::table('settings')->select("site_logo")->where("setting_id", 1)->first();
        $site_logo = asset('default/a-logo.png');
        if ($settings->site_logo) {
            // $exists = Storage::disk(config('app.filesystem_driver'))->exists('public/uploads/logos/'.$settings->site_logo);
            // if($exists){ 
            $site_logo = asset(Storage::url('public/uploads/logos') . '/' . $settings->site_logo);
            // }else{ 
            //     $site_logo= asset('imgs/a-logo.png');
            // } 
            // $site_logo = $settings->site_logo;
        }
        return $site_logo;
    }

    public static function getFrontWhiteLogo()
    {
        $settings = DB::table('home_settings')->select("white_logo")->where("home_setting_id", 1)->first();
        $logo = asset('default') . '/w-logo.png';
        if ($settings->white_logo) {

            // $exists = Storage::disk(config('app.filesystem_driver'))->exists('public/uploads/logos/'.$settings->logo);
            // if($exists){ 
            $logo = asset(Storage::url('public/uploads/logos') . '/' . $settings->white_logo);
            // }else{ 
            //     $logo= asset('imgs/w-logo.png');
            // } 

            // $logo = asset('storage/uploads/logos/'.$settings->logo);
        }
        return $logo;
    }

    public static function getFrontLogo()
    {
        $settings = DB::table('home_settings')->select("logo")->where("home_setting_id", 1)->first();
        $logo = asset('default') . '/logo.png';
        if ($settings->logo) {
            $logo = asset(Storage::url('public/uploads/logos') . '/' . $settings->logo);
        }
        return $logo;
    }

    public static function checkSocialLogin()
    {
        $social = array();
        $settings = DB::table('social_settings')->where("social_setting_id", 1)->first();
        $social['fb_active'] = 0;
        $social['google_active'] = 0;
        if (isset($settings)) {
            if ($settings->google_active == 1) {
                $social['google_active'] = 1;
            }
            if ($settings->fb_active == 1) {
                $social['fb_active'] = 1;
            }
        }

        return $social;
    }
    public static function getSiteTitle()
    {
        $isColExist = Schema::hasColumn('home_settings', 'site_title');
        $site_title = "Leuke";
        if ($isColExist) {
            $settings = DB::table('home_settings')->select("site_title")->where("home_setting_id", 1)->first();
            if (isset($settings->site_title)) {
                $site_title = $settings->site_title;
            }
        }

        return $site_title;
    }

    public static function setEnvironmentValue(array $values)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);
        if (count($values) > 0) {
            foreach ($values as $envKey => $envValue) {
                $env_str = explode("\n", $str); // In case the searched variable is in the last line without \n
                foreach ($env_str as $k => $v) {
                    $i = 0;
                    $env_key = explode('=', $v);
                    if (strcmp($env_key[0], $envKey) == 0) {
                        $env_str[$k] = $envKey . '=' . $envValue;
                        $i = 0;
                        break;
                    } else {
                        $i = 1;
                    }
                }
                if ($i == 1) {
                    array_push($env_str, $envKey . '=' . $envValue);
                }
                $str = implode("\n", $env_str);
            }
        }
        if (!file_put_contents($envFile, $str)) return false;
        return true;
    }
    public static function setEnvironmentValue2(array $values)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);
        if (count($values) > 0) {
            foreach ($values as $envKey => $envValue) {

                $str .= "\n"; // In case the searched variable is in the last line without \n
                // dd($str);
                $keyPosition = strpos($str, "{$envKey}=");
                $endOfLinePosition = strpos($str, "\n", $keyPosition);
                $oldLine = substr($str, $keyPosition, $endOfLinePosition - $keyPosition);

                // If key does not exist, add it
                if (!$keyPosition || !$endOfLinePosition || !$oldLine) {
                    $str .= "{$envKey}={$envValue}\n";
                } else {
                    $str = str_replace($oldLine, "{$envKey}={$envValue}", $str);
                }
            }
        }
        $str = substr($str, 0, -1);

        if (!file_put_contents($envFile, $str)) return false;
        return true;
    }

    public static function getProfileImageUrl($user)
    {
        $profileImg = '';
        if (!empty($user->user_dp)) {
            if ((strpos($user->user_dp, 'facebook.com') !== false) || (strpos($user->user_dp, 'fbsbx.com') !== false) || (strpos($user->user_dp, 'googleusercontent.com') !== false)) {
                $profileImg = $user->user_dp;
            } else {
                // $exists = Storage::disk(config('app.filesystem_driver'))->exists('public/profile_pic/'.$user->user_id.'/small/'.$user->user_dp);
                // if($exists){
                // if(file_exists(public_path('storage/profile_pic').'/'.$user->user_id.'/small/'.$user->user_dp)){
                $profileImg = asset(Storage::url('public/profile_pic/' . $user->user_id . '/small/' . $user->user_dp));
                // }else{
                //     $profileImg=asset('storage/profile_pic/default.png');
                // } 
            }
        } else {
            $profileImg = asset('default/default.png');
        }
        return $profileImg;
    }



    public static function getVideoThumbUrl($video)
    {
        $videoThumb = asset(Storage::url('public/videos/' . $video->user_id . '/thumb/' . $video->thumb));
        return $videoThumb;
    }

    public static function getSuggestionAccount()
    {
        if (Auth::user()) {
            $id = Auth::user()->user_id;
        } else {
            $id = 0;
        }

        $records = DB::table('users as u')
            ->select(DB::raw('u.user_id,u.username,u.fname,u.lname,u.user_dp,ifnull(f2.follow_id,0) as follow,uv.verified'))
            // ->leftJoin('user_verify as uv','uv.user_id','u.user_id')
            ->leftJoin('user_verify as uv', function ($join) {
                $join->on('uv.user_id', '=', 'u.user_id')
                    ->where('uv.verified', 'A');
            })
            ->leftJoin('follow as f2', function ($join) use ($id) {
                $join->on('u.user_id', '=', 'f2.follow_to')
                    ->where('f2.follow_by', $id);
            });
        if ($id > 0) {
            $records = $records->whereRaw(DB::Raw(' f2.follow_id is null '));

            $records = $records->leftJoin('blocked_users as bu', function ($join) use ($id) {
                $join->on('u.user_id', '=', 'bu.user_id');
                $join->whereRaw(DB::raw(" ( bu.blocked_by=" . $id . " )"));
            });

            $records = $records->leftJoin('blocked_users as bu2', function ($join) use ($id) {
                $join->on('u.user_id', '=', 'bu2.blocked_by');
                $join->whereRaw(DB::raw(" (  bu2.user_id=" . $id . " )"));
            });

            $records = $records->whereRaw(DB::Raw(' bu.block_id is null and bu2.block_id is null '));
        };
        $records = $records->where('u.user_id', '!=', $id)
            ->where('active', 1)
            ->where('deleted', 0)
            ->groupBy(DB::raw('u.user_id,u.username,u.fname,u.lname,u.user_dp'))
            ->orderBy('user_id', 'desc')
            ->limit(5)
            ->get();

        return $records;
    }

    public static function getSocialMediaLinks()
    {


        $links = DB::table('social_media_links')
            ->select(DB::raw('*'))
            ->first();

        return $links;
    }

    public static function getSponsors()
    {


        $sponsors = DB::table('sponsors')
            ->select(DB::raw('*'))
            ->where('active', 1)
            ->get();

        return $sponsors;
    }

    public static function getTopbarColor()
    {
        $color = DB::table('home_settings')
            ->select(DB::raw('main_color'))
            ->first();
        $topbarColor = 'background: linear-gradient(50deg,rgb(115,80,199) 0%,rgb(236,74,99) 100%);';
        if (isset($color->main_color)) {
            $topbarColor = $color->main_color;
        }
        return $topbarColor;
    }

    public static function rgb2hex2rgb($color)
    {
        if (!$color) return false;
        $color = trim($color);
        $result = false;
        if (preg_match("/^[0-9ABCDEFabcdef\#]+$/i", $color)) {
            $hex = str_replace('#', '', $color);
            if (!$hex) return false;
            if (strlen($hex) == 3) :
                $result['r'] = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
                $result['g'] = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
                $result['b'] = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
            else :
                $result['r'] = hexdec(substr($hex, 0, 2));
                $result['g'] = hexdec(substr($hex, 2, 2));
                $result['b'] = hexdec(substr($hex, 4, 2));
            endif;
        } elseif (preg_match("/^[0-9]+(,| |.)+[0-9]+(,| |.)+[0-9]+$/i", $color)) {
            $rgbstr = str_replace(array(',', ' ', '.'), ':', $color);
            $rgbarr = explode(":", $rgbstr);
            $result = '#';
            $result .= str_pad(dechex($rgbarr[0]), 2, "0", STR_PAD_LEFT);
            $result .= str_pad(dechex($rgbarr[1]), 2, "0", STR_PAD_LEFT);
            $result .= str_pad(dechex($rgbarr[2]), 2, "0", STR_PAD_LEFT);
            $result = strtoupper($result);
        } else {
            $result = false;
        }

        return $result;
    }
    public static function rgb2HEXhtml($r, $g = -1, $b = -1)
    {
        if (is_array($r) && sizeof($r) == 3)
            list($r, $g, $b) = $r;
        $r = intval($r);
        $g = intval($g);
        $b = intval($b);
        $r = dechex($r < 0 ? 0 : ($r > 255 ? 255 : $r));
        $g = dechex($g < 0 ? 0 : ($g > 255 ? 255 : $g));
        $b = dechex($b < 0 ? 0 : ($b > 255 ? 255 : $b));
        $color = (strlen($r) < 2 ? '0' : '') . $r;
        $color .= (strlen($g) < 2 ? '0' : '') . $g;
        $color .= (strlen($b) < 2 ? '0' : '') . $b;
        $color = '#' . $color;
        return $color;
    }

    public static function getProfilepic($user_id, $user_dp)
    {
        if ($user_dp != "") {
            if (strpos($user_dp, 'facebook.com') !== false || strpos($user_dp, 'fbsbx.com') !== false || strpos($user_dp, 'googleusercontent.com') !== false) {
                $img =  $user_dp;
            } else {
                $img = asset(Storage::url('public/profile_pic/' . $user_id . '/small/' . $user_dp));
            }
        } else {
            $img = asset('default/default.png');
        }
        return $img;
    }
    public static function getCurrentVersion()
    {
        $cur_version = "v2.0";

        $settings = DB::table('settings')->select("cur_version")->where("setting_id", 1)->first();
        if (isset($settings->cur_version)) {
            $cur_version = $settings->cur_version;
        }


        return $cur_version;
    }

    public static function checkUserVerified()
    {
        $res = 0;

        $data = DB::table('user_verify')->select("verified")->where("user_id", Auth::user()->user_id)->orderBy('user_verify_id', 'desc')->first();
        if (isset($data->verified) && $data->verified == 'A') {
            $res = 1;
        }


        return $res;
    }

    public static function userFollowingCount($id)
    {
        if (Auth::guard('web')->check()) {
            $user_id = Auth::user()->user_id;
        } else {
            $user_id = 0;
        }
        $following = DB::table('follow as f')
            ->leftJoin('users as u', 'u.user_id', 'f.follow_to');
        if ($user_id > 0) {
            $following = $following->leftJoin('blocked_users as bu', function ($join) use ($user_id) {
                $join->on('u.user_id', '=', 'bu.user_id');
                $join->whereRaw(DB::raw(" ( bu.blocked_by=" . $user_id . " )"));
            });

            $following = $following->leftJoin('blocked_users as bu2', function ($join) use ($user_id) {
                $join->on('u.user_id', '=', 'bu2.blocked_by');
                $join->whereRaw(DB::raw(" (  bu2.user_id=" . $user_id . " )"));
            });

            $following = $following->whereRaw(DB::Raw(' bu.block_id is null and bu2.block_id is null '));
        }
        $following = $following->where('f.follow_by', $id)
            ->where('u.active', 1)
            ->where('u.deleted', 0)->count();

        return $following;
    }

    public static function userFollowersCount($id)
    {
        if (Auth::guard('web')->check()) {
            $user_id = Auth::user()->user_id;
        } else {
            $user_id = 0;
        }
        $followers = DB::table('follow as f')
            ->leftJoin('users as u', 'u.user_id', 'f.follow_by');
        if ($user_id > 0) {
            $followers = $followers->leftJoin('blocked_users as bu', function ($join) use ($user_id) {
                $join->on('u.user_id', '=', 'bu.user_id');
                $join->whereRaw(DB::raw(" ( bu.blocked_by=" . $user_id . " )"));
            });

            $followers = $followers->leftJoin('blocked_users as bu2', function ($join) use ($user_id) {
                $join->on('u.user_id', '=', 'bu2.blocked_by');
                $join->whereRaw(DB::raw(" (  bu2.user_id=" . $user_id . " )"));
            });

            $followers = $followers->whereRaw(DB::Raw(' bu.block_id is null and bu2.block_id is null '));
        }
        $followers = $followers->where('f.follow_to', $id)
            ->where('u.active', 1)
            ->where('u.deleted', 0)->count();
        return $followers;
    }

    public static function ffmpegCheckVideoStream($vidoeFilePath)
    {
        $ffmpeg = FFMpeg\FFMpeg::create(array(
            'ffmpeg.binaries'  => config('app.ffmpeg'),
            'ffprobe.binaries' => config('app.ffprobe'),
            'timeout'          => 3600, // The timeout for the underlying process
            'ffmpeg.threads'   => 12, // The number of threads that FFMpeg should use
        ));
        $ffprobe =  FFMpeg\FFProbe::create(array(
            'ffmpeg.binaries'  => config('app.ffmpeg'),
            'ffprobe.binaries' => config('app.ffprobe'),
            'timeout'          => 3600, // The timeout for the underlying process
            'ffmpeg.threads'   => 12, // The number of threads that FFMpeg should use
        ));
        // $ffprobe = FFMpeg\FFProbe::create();
        $streamCount = $ffprobe->streams($vidoeFilePath)->audios()->count();

        return $streamCount;
    }

    public static function ffmpegUpload($vidoeFilePath, $videoStorePath = '', $audioFilePath = '', $audioStorePath = '', $thumbStorePath = '', $watermarkPath = '', $storageDrive = "local", $s3VideoFolder = '', $s3VideoFileName = '', $s3AudioFolder = '', $s3AudioFileName = '', $s3ThumbFolder = '', $s3ThumbFileName = '')
    {
        $ffmpeg = FFMpeg\FFMpeg::create(array(
            'ffmpeg.binaries'  => config('app.ffmpeg'),
            'ffprobe.binaries' => config('app.ffprobe'),
            'timeout'          => 3600, // The timeout for the underlying process
            'ffmpeg.threads'   => 12, // The number of threads that FFMpeg should use
        ));
        $ffprobe =  FFMpeg\FFProbe::create(array(
            'ffmpeg.binaries'  => config('app.ffmpeg'),
            'ffprobe.binaries' => config('app.ffprobe'),
            'timeout'          => 3600, // The timeout for the underlying process
            'ffmpeg.threads'   => 12, // The number of threads that FFMpeg should use
        ));

        // $ffmpeg = FFMpeg\FFMpeg::create();
        // $ffprobe = FFMpeg\FFProbe::create();
        if ($vidoeFilePath != "" && $audioFilePath == "" && $watermarkPath == "") {
            // watermark (no) and audio(no)
            $streamCount = $ffprobe->streams($vidoeFilePath)->audios()->count();

            if ($streamCount > 0) {
                $audio = $ffmpeg->open($vidoeFilePath);
                $audio_format = new FFMpeg\Format\Audio\Mp3();

                $audio->save($audio_format, $audioStorePath);

                if ($storageDrive == 's3') {
                    $file = new File($audioStorePath);
                    Storage::putFileAs($s3AudioFolder, $file, $s3AudioFileName);
                    Storage::setVisibility($s3AudioFolder . '/' . $s3AudioFileName, 'public');
                    // dd(Storage::url($s3AudioFolder.'/'.$s3AudioFileName));
                    // unlink($audioStorePath);
                }
            } else {
                $response = array("status" => "error", 'msg' => 'video format invalid.Please choose another video.');
                return $response;
            }

            $video = $ffmpeg->open($vidoeFilePath);
            $format = new FFMpeg\Format\Video\X264();
            $format->setAudioCodec("aac");

            $format->setAdditionalParameters(array('-vf', 'scale=720:-2', '-preset', 'ultrafast'));
            $video
                ->save($format, $videoStorePath);

            $video = $ffmpeg->open($vidoeFilePath);
            $video
                ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(0))
                ->save($thumbStorePath);
            if ($storageDrive == 's3') {
                $img = new File($thumbStorePath);
                Storage::putFileAs($s3ThumbFolder, $img, $s3ThumbFileName);
                Storage::setVisibility($s3ThumbFolder . '/' . $s3ThumbFileName, 'public');
                unlink($thumbStorePath);
            }

            if ($storageDrive == 's3') {
                $video = new File($videoStorePath);
                Storage::disk('s3')->putFileAs($s3VideoFolder, $video, $s3VideoFileName);
                Storage::disk('s3')->setVisibility($s3VideoFolder . '/' . $s3VideoFileName, 'public');
                unlink($videoStorePath);
                Storage::disk('local')->deleteDirectory($s3VideoFolder);
            }
        } elseif ($vidoeFilePath != "" && $audioFilePath != "" && $watermarkPath == "") {
            // watermark no and audio yes
            $advancedMedia = $ffmpeg->openAdvanced(array($audioFilePath, $vidoeFilePath));

            $advancedMedia->filters()
                ->custom('[1:v]', 'scale=720:-2', '[v]');

            $advancedMedia
                ->map(array('0:a', '[v]'), new X264('aac', 'libx264'), $videoStorePath);

            $advancedMedia->save();

            $video = $ffmpeg->open($vidoeFilePath);
            $video
                ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(0))
                ->save($thumbStorePath);
            if ($storageDrive == 's3') {
                $img = new File($thumbStorePath);
                Storage::putFileAs($s3ThumbFolder, $img, $s3ThumbFileName);
                Storage::setVisibility($s3ThumbFolder . '/' . $s3ThumbFileName, 'public');
                unlink($thumbStorePath);
                // dd(Storage::url($s3ThumbFolder.'/'.$s3ThumbFileName));
            }

            if ($storageDrive == 's3') {
                $video = new File($videoStorePath);
                Storage::disk('s3')->putFileAs($s3VideoFolder, $video, $s3VideoFileName);
                Storage::disk('s3')->setVisibility($s3VideoFolder . '/' . $s3VideoFileName, 'public');
                unlink($videoStorePath);
                Storage::disk('local')->deleteDirectory($s3VideoFolder);
            }
        } elseif ($vidoeFilePath != "" && $audioFilePath == "" && $watermarkPath != "") {
            // dd($vidoeFilePath);
            $streamCount = $ffprobe->streams($vidoeFilePath)->audios()->count();
            //             $streamCount = $ffprobe->streams($vidoeFilePath);

            if ($streamCount > 0) {
                $video = $ffmpeg->open($vidoeFilePath);
                $audio_format = new FFMpeg\Format\Audio\Mp3();

                $video->save($audio_format, $audioStorePath);

                if ($storageDrive == 's3') {
                    $file = new File($audioStorePath);
                    Storage::putFileAs($s3AudioFolder, $file, $s3AudioFileName);
                    Storage::setVisibility($s3AudioFolder . '/' . $s3AudioFileName, 'public');
                }
            } else {
                $response = array("status" => "error", 'msg' => 'video format invalid.Please choose another video.');
                return $response;
            }

            $advancedMedia = $ffmpeg->openAdvanced(array($watermarkPath, $vidoeFilePath));
            $advancedMedia->filters()
                // ->custom('[1:v]', 'overlay=W-w-5:5', '[v]');
                ->custom('[1:v]scale=720:-2,', 'overlay=W-w-5:5', '[v]');
            $advancedMedia
                ->map(array('1:a', '[v]'), new X264('aac', 'libx264'), $videoStorePath);
            $advancedMedia->save();

            $video = $ffmpeg->open($vidoeFilePath);
            $video
                ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(0))
                ->save($thumbStorePath);
            if ($storageDrive == 's3') {
                $img = new File($thumbStorePath);
                Storage::putFileAs($s3ThumbFolder, $img, $s3ThumbFileName);
                Storage::setVisibility($s3ThumbFolder . '/' . $s3ThumbFileName, 'public');
                unlink($thumbStorePath);
            }

            if ($storageDrive == 's3') {
                $video = new File($videoStorePath);
                Storage::disk('s3')->putFileAs($s3VideoFolder, $video, $s3VideoFileName);
                Storage::disk('s3')->setVisibility($s3VideoFolder . '/' . $s3VideoFileName, 'public');
                unlink($videoStorePath);
                Storage::disk('local')->deleteDirectory($s3VideoFolder);
            }
        } elseif ($vidoeFilePath != "" && $audioFilePath != "" && $watermarkPath != "") {
            // watermark yes and audio yes

            $advancedMedia = $ffmpeg->openAdvanced(array($watermarkPath, $audioFilePath, $vidoeFilePath));

            $advancedMedia->filters()
                ->custom('[2:v]scale=720:-2,', 'overlay=W-w-5:5', '[v]');
            $advancedMedia
                ->map(array('1:a', '[v]'), new X264('aac', 'libx264'), $videoStorePath);
            $advancedMedia->save();


            $video = $ffmpeg->open($vidoeFilePath);
            $video
                ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(0))
                ->save($thumbStorePath);
            if ($storageDrive == 's3') {
                $img = new File($thumbStorePath);
                Storage::putFileAs($s3ThumbFolder, $img, $s3ThumbFileName);
                Storage::setVisibility($s3ThumbFolder . '/' . $s3ThumbFileName, 'public');
                unlink($thumbStorePath);
                // dd(Storage::url($s3ThumbFolder.'/'.$s3ThumbFileName));
            }

            if ($storageDrive == 's3') {
                $video = new File($videoStorePath);
                Storage::disk('s3')->putFileAs($s3VideoFolder, $video, $s3VideoFileName);
                Storage::disk('s3')->setVisibility($s3VideoFolder . '/' . $s3VideoFileName, 'public');
                unlink($videoStorePath);
                Storage::disk('local')->deleteDirectory($s3VideoFolder);
            }
        }
        $response = array("status" => "success", 'msg' => 'Video Uploaded Successfully.');
        return $response;
    }
    public static function ffmpegUploadApi($vidoeFilePath, $videoStorePath = '', $audioStorePath = '', $storageDrive = "local", $s3VideoFolder = '', $s3VideoFileName = '', $s3AudioFolder = '', $s3AudioFileName = '')
    {
        $ffmpeg = FFMpeg\FFMpeg::create(array(
            'ffmpeg.binaries'  => config('app.ffmpeg'),
            'ffprobe.binaries' => config('app.ffprobe'),
            'timeout'          => 3600, // The timeout for the underlying process
            'ffmpeg.threads'   => 12, // The number of threads that FFMpeg should use
        ));
        $ffprobe =  FFMpeg\FFProbe::create(array(
            'ffmpeg.binaries'  => config('app.ffmpeg'),
            'ffprobe.binaries' => config('app.ffprobe'),
            'timeout'          => 3600, // The timeout for the underlying process
            'ffmpeg.threads'   => 12, // The number of threads that FFMpeg should use
        ));

        // $ffmpeg = FFMpeg\FFMpeg::create();
        // $ffprobe = FFMpeg\FFProbe::create();
        if ($vidoeFilePath != "") {
            // watermark (no) and audio(no)
            $streamCount = $ffprobe->streams($vidoeFilePath)->audios()->count();

            if ($streamCount > 0) {
                $audio = $ffmpeg->open($vidoeFilePath);
                $audio_format = new FFMpeg\Format\Audio\Mp3();

                $audio->save($audio_format, $audioStorePath);

                if ($storageDrive == 's3') {
                    $file = new File($audioStorePath);
                    Storage::putFileAs($s3AudioFolder, $file, $s3AudioFileName);
                    Storage::setVisibility($s3AudioFolder . '/' . $s3AudioFileName, 'public');
                    // dd(Storage::url($s3AudioFolder.'/'.$s3AudioFileName));
                    // unlink($audioStorePath);
                }
            } else {
                $response = array("status" => "error", 'msg' => 'video format invalid.Please choose another video.');
                return $response;
            }
            // $video = $ffmpeg->open($vidoeFilePath);
            // $format = new FFMpeg\Format\Video\X264();
            // $format->setAudioCodec("aac");

            // $format->setAdditionalParameters(array('-vf', 'scale=720:-2','-preset','ultrafast'));
            // $video
            //     ->save($format, $videoStorePath);


            //     if($storageDrive=='s3'){
            //         $video=new File($videoStorePath);
            //         Storage::disk('s3')->putFileAs($s3VideoFolder, $video,$s3VideoFileName);
            //         Storage::disk('s3')->setVisibility($s3VideoFolder.'/'.$s3VideoFileName, 'public');
            //         unlink($videoStorePath);
            //         Storage::disk('local')->deleteDirectory($s3VideoFolder);
            //     }
        }
        $response = array("status" => "success", 'msg' => 'Video Uploaded Successfully.');
        return $response;
    }

    public static function getConversations()
    {
        $user_id = auth()->user('web')->user_id;

        $conversations = Conversation::whereHas('chats',function($q) use($user_id){
            $q->whereHas('message',function($q) use($user_id){
                $q->where('user_id',$user_id);
            });
        })->with(array('messages_list' => function ($q) {
            $q->orderBy('created_at', 'DESC');
        }))
            ->selectRaw("chat_conversations.*, (SELECT COUNT(IFNULL(chat_chats.read_at,0)) from chat_chats where IFNULL(chat_chats.read_at,0)=0 and chat_chats.type=0 and chat_chats.user_id!=$user_id and chat_conversations.id=chat_chats.conversation_id) as count,(SELECT MAX(created_at) from chat_messages WHERE chat_messages.conversation_id=chat_conversations.id) as latest_message_on")
            // ->selectRaw("chat_conversations.*, (SELECT chat_chats.read_at from chat_chats where chat_chats.user_id!=$user_id and chat_conversations.id=chat_chats.conversation_id) as count,(SELECT MAX(created_at) from chat_messages WHERE chat_messages.conversation_id=chat_conversations.id) as latest_message_on")
            ->whereRaw(DB::raw("(chat_conversations.user_from=$user_id or chat_conversations.user_to=$user_id)"))
            ->orderBy("latest_message_on", "DESC")
            ->paginate(10);

        // dd($conversations);
        $conversation = [];
        $conver = [];
        if ($conversations) {
            foreach ($conversations as $con) {
                // $conversation=$con;
                $user_from = $con->user_from;
                $user_to = $con->user_to;
                if ($user_from == $user_id) {
                    $from_user = $user_to;
                } else {
                    $from_user = $user_from;
                }

                $userDpPath = asset(Storage::url('public/profile_pic'));
                $user = DB::table('users')->select(DB::raw("*,case when user_dp !='' THEN case when INSTR(user_dp,'https://') > 0 THEN user_dp ELSE concat('" . $userDpPath . "/',user_id,'/small/',user_dp)  END ELSE '' END as user_dp"))->where('user_id', $from_user)->first();

                $conversation['id'] = $con->id;
                $conversation['user_id'] = $user->user_id;
                $conversation['person_name'] = $user->fname . ' ' . $user->lname;
                $conversation['username'] = $user->username;
                $conversation['user_dp'] = $user->user_dp;
                $conversation['message'] = $con->messages_list->first()->msg;
                $conversation['timestamp'] = strtotime($con->messages_list->first()->created_at);
                $conversation['time'] = \Carbon\Carbon::parse($con->messages_list->first()->created_at)->diffForHumans();
                $conversation['count']=$con->count;
                // $conversation = $custom->merge($conversation);
                array_push($conver, $conversation);
            }
        }
        $response = array("status" => "success", 'data' => $conver);
        // $conver=$conversation;
        return $response;
    }

    public static function chatUsers()
    {

        if (auth()->guard('web')->user()) {
            $login_id = auth()->guard('web')->user()->user_id;
            $userDpPath = asset(Storage::url('public/profile_pic'));
            $limit = 10;
            $user = User::find($login_id);
            if ($user->chat_with == 'FL') {
                //following
                $users = DB::table("users as u")->select(DB::raw("u.user_id,
                    case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('" . $userDpPath . "/',u.user_id,'/small/',u.user_dp)  END ELSE '' END as user_dp,
                    concat('@',u.username) as username,u.fname,u.lname, case when f2.follow_id > 0 THEN 'Following' ELSE 'Follow' END as followText"))
                    ->leftJoin('follow as f', function ($join) {
                        $join->on('u.user_id', '=', 'f.follow_to');
                        // ->where('f.follow_by',$request->login_id);
                    })
                    ->leftJoin('follow as f2', function ($join) use ($login_id) {
                        $join->on('u.user_id', '=', 'f2.follow_to')
                            ->where('f2.follow_by', $login_id);
                    });
                if ($login_id > 0) {
                    $users = $users->leftJoin('blocked_users as bu', function ($join) use ($login_id) {
                        $join->on('u.user_id', '=', 'bu.user_id');
                        $join->whereRaw(DB::raw(" ( bu.blocked_by=" . $login_id . " )"));
                    });

                    $users = $users->leftJoin('blocked_users as bu2', function ($join) use ($login_id) {
                        $join->on('u.user_id', '=', 'bu2.blocked_by');
                        $join->whereRaw(DB::raw(" (  bu2.user_id=" . $login_id . " )"));
                    });

                    $users = $users->whereRaw(DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                }
                $users = $users->where('f.follow_to', '<>', $login_id);
                $users = $users->where('f.follow_by', $login_id);
                $users = $users->where("u.deleted", 0)
                    ->where("u.active", 1);

                if (isset($search) && $search != "") {
                    // $search = $search;
                    $users = $users->where('u.username', 'like', '%' . $search . '%')->orWhere('u.fname', 'like', '%' . $search . '%')->orWhere('u.lname', 'like', '%' . $search . '%');
                }

                $users = $users->orderBy('u.user_id', 'desc');
                $users = $users->paginate($limit);
            } else {
                //followers
                $users = DB::table("users as u")->select(DB::raw("u.user_id,
                    case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('" . $userDpPath . "/',u.user_id,'/small/',u.user_dp)  END ELSE '' END as user_dp,
                    concat('@',u.username) as username,u.fname,u.lname, case when f2.follow_id > 0 THEN 'Following' ELSE 'Follow' END as followText"))
                    ->leftJoin('follow as f', function ($join) {
                        $join->on('u.user_id', '=', 'f.follow_by');
                        // ->where('f.follow_to',$request->login_id);
                    })
                    ->leftJoin('follow as f2', function ($join) use ($login_id) {
                        $join->on('u.user_id', '=', 'f2.follow_to')
                            ->where('f2.follow_by', $login_id);
                    });
                if ($login_id > 0) {
                    $users = $users->leftJoin('blocked_users as bu', function ($join) use ($login_id) {
                        $join->on('u.user_id', '=', 'bu.user_id');
                        $join->whereRaw(DB::raw(" ( bu.blocked_by=" . $login_id . " )"));
                    });

                    $users = $users->leftJoin('blocked_users as bu2', function ($join) use ($login_id) {
                        $join->on('u.user_id', '=', 'bu2.blocked_by');
                        $join->whereRaw(DB::raw(" (  bu2.user_id=" . $login_id . " )"));
                    });

                    $users = $users->whereRaw(DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                }
                $users = $users->where('f.follow_by', '<>', $login_id);
                $users = $users->where('f.follow_to', $login_id);
                $users = $users->where("u.deleted", 0)
                    ->where("u.active", 1);

                if (isset($search) && $search != "") {
                    // $search = $search;
                    $users = $users->where('u.username', 'like', '%' . $search . '%')->orWhere('u.fname', 'like', '%' . $search . '%')->orWhere('u.lname', 'like', '%' . $search . '%');
                }

                $users = $users->orderBy('u.user_id', 'desc');
                $users = $users->paginate($limit);
            }
            // dd($users);
            $total_records = $users->total();

            $response = array("status" => "success", 'data' => $users, 'total_records' => $total_records);
            return $response;
        } else {
            return response()->json([
                "status" => "error", "msg" => "Unauthorized user!"
            ]);
        }


        // return response()->json($response);
    }
}
