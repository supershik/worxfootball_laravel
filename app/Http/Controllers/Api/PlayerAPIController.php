<?php
namespace App\Http\Controllers\Api;
use App\Activity;
use App\Booking;
use App\Http\Controllers\Controller;
use App\Match;
use App\Player;
use App\User;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Validator;
class PlayerAPIController extends Controller
{
    public $successStatus = 200;
    public function login() {
        $request = ['email' => request('email'), 'password' => request('password')];

        $validator = Validator::make($request, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails($request)) {
            return response()->json(['error'=>$validator->errors()], 401);
        }

        $player = Player::where('email', $request['email'])->first();

        if( $player != null ) {    // email successful
            if (Hash::check($request['password'], $player['password'])) {   // password successful
                return response()->json(['data' => ['user' => $player]], $this-> successStatus);
            }
        }

        return response()->json(['error'=>'Unauthenticated player'], 401);
    }
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => [
                'required',
                'unique:players',
            ],
            'password' => [
                'required',
            ],
            'first_name' => [
                'required',
            ],
            'last_name' => [
                'required',
            ],
            'birthday' => [
                'required',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 401);
        }

        if( $request->hasFile('photo') ) {
            $file = $request->file('photo');
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '.' . $extension;
            $file->move('uploads/photo/', $filename);
            $request->merge(['photo' => $filename]);

        } else {
            $request->merge(['photo' => 'photo_empty.png']);
        }

        $player = Player::where('email','=',$request['email']);
        if( $player->count()) {
            return response()->json(['error'=>'The email exist already.'], 401);
        }

        $player = Player::create($request->all());
        $success['email'] =  $player->email;
        return response()->json(['data' => ['user' => $player]], $this-> successStatus);
    }

    public function players()
    {
        $players = Player::all();
        return response()->json(['data' => ['users' => $players]], $this-> successStatus);
    }
    public function info(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'player_id' => [
                'required',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 401);
        }

        $player = Player::where('id', '=', $request['player_id'])->first();

        if(empty($player))
            return response()->json(['data' => ['success' => false]] , 401);

        $bookings = Booking::selectRaw('bookings.*, matches.*')
                            ->where('matches.start_time', '<', now())
                            ->leftJoin('matches', 'bookings.match_id', '=', 'matches.id' )
                            ->where('player_id', '=', $player->id)
                            ->get();

        return response()->json(['data' => ['user' => $player, 'match_count' => count($bookings)]] , $this-> successStatus);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'player_id' => [
                'required',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 401);
        }

        $player = Player::where('id', '=', $request['player_id'])->first();

        if(empty($player))
            return response()->json(['data' => ['success' => false]] , 401);

        if( $request->hasFile('photo') ) {
            $path = 'uploads/photo/' . $player->getAttribute('photo');
//            if (Storage::exists($path)) {
//                Storage::delete($path);
//            }

            if($path != "uploads/photo/photo_empty.png" && File::isFile($path)) {
                File::delete($path);
            }

            $file = $request->file('photo');
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '.' . $extension;
            $file->move('uploads/photo/', $filename);
            $request->merge(['photo' => $filename]);

        } else {

        }

        $player->update($request->input());

        return response()->json(['data' => ['success' => true]], $this-> successStatus);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'player_id' => [
                'required',
            ],
            'content' => [
                'required',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 401);
        }

        $player = Player::where('id', '=', $request['player_id'])->first();
        if(empty($player))
            return response()->json(['error'=>'Player is not exist.'], 401);

        $player->update(['status' => 3]);
        $player->delete();


        $activity = Activity::where('player_id', '=', $request['player_id'])
            ->where('type', '=', 3)
            ->first();

        if(!empty($activity) && $activity['type'] == 3) {
            $activity->delete();
        }

        $info = [
            'player_id' => $request['player_id'],
            'type' => 3,    // deleted
            'content' => $request['content'],
        ];

        Activity::create($info);

        return response()->json(['data' => ['success' => true]] , $this-> successStatus);
    }
}
