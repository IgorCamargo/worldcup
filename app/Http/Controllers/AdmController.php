<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use DB;

class AdmController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware( 'auth' );
    }

    /**
     * Update matches results
     */
    public function index()
    {
        $user = auth()->user();

        if (( $user->id == 1 ) && ( $user->username == 'igorfelcam' )) {
            // matches soccers
            $matches_soccers = DB::table( 'matches_soccers as mat' )
                                    ->select(
                                        'mat.id as match_id',
                                        'typ.id as type_id',
                                        'typ.name as type_name',
                                        'mat.first_team_id as team_a_id',
                                        'tea.url_flag as flag_a',
                                        'tea.nickname as team_a',
                                        'mat.second_team_id as team_b_id',
                                        'teb.url_flag as flag_b',
                                        'teb.nickname as team_b',
                                        DB::raw( 'date_format( mat.match_date, "%d/%m/%Y - %H:%i" ) as match_date' ),
                                        'mat.first_team_goals as bet_first_team_goals',
                                        'mat.second_team_goals as bet_second_team_goals'
                                    )
                                    ->join( 'type_matches as typ', 'mat.type_match_id', '=', 'typ.id' )
                                    ->join( 'teams as tea', 'mat.first_team_id', '=', 'tea.id' )
                                    ->join( 'teams as teb', 'mat.second_team_id', '=', 'teb.id' )
                                    ->orderBy( 'mat.match_date', 'asc' )
                                    ->get();

            return view( 'adm_matches' )->with([
                                    'matches_soccers' => $matches_soccers,
                                ]);
        }
        else {
            return redirect()->route('home');
        }

    }

    /**
     * Update matches results
     */
    public function results( Request $request )
    {
        $user = auth()->user();

        if (( $user->id == 1 ) && ( $user->username == 'igorfelcam' )) {

            $match_id = $request->input( 'match_id' );
            $match_date = $request->input( 'match_date' );
            $team_first = $request->input( 'team_first' );
            $team_second = $request->input( 'team_second' );

            date_default_timezone_set( 'America/Sao_Paulo' );
            $date_now = date( 'Y-m-d H:i:s' );

            $valid_first = preg_match( "/^[0-9]+$/", $team_first );
            $valid_second = preg_match( "/^[0-9]+$/", $team_second );

            $match_date = DB::table( 'matches_soccers' )
                                ->select( 'match_date' )
                                ->where( 'id', '=', $match_id )
                                ->get();

            $match_date = $match_date[0]->match_date;

            if ( $team_first != null && $team_second != null ) {
                if (
                    ( $match_id != null ) &&
                    ( $match_date < $date_now ) &&
                    ( $valid_first || $team_first == null ) &&
                    ( $valid_second || $team_second == null )
                ) {
                    if ( $team_first == null ) {
                        $team_first = 0;
                    }
                    elseif ( $team_second == null ) {
                        $team_second = 0;
                    }
                    elseif ( $team_first != null ) {
                        $team_first = (String) $team_first;
                    }
                    elseif ( $team_second != null ) {
                        $team_second = (String) $team_second;
                    }

                    // update match result
                    DB::table( 'matches_soccers' )
                    ->where( 'id', $match_id )
                    ->update([
                        'first_team_goals'   => (String) $team_first,
                        'second_team_goals'  => (String) $team_second
                    ]);

                    // betting scoring
                    $this->bettingScoring();
                    // select all users
                    $users = DB::table( 'users' )
                                ->select( 'id' )
                                ->get();
                    // get total score
                    foreach ( $users as $us ) {
                        $total_score = DB::table( 'bets' )
                                            ->where( 'user_id', '=', $us->id )
                                            ->sum( 'score' );
                        // update user total score
                        if ( $total_score >= 0 ) {
                            DB::table( 'users' )
                                ->where( 'id', $us->id )
                                ->update([ 'total_score' => $total_score ]);
                        }
                    }
                }
            }
        }
        return redirect()->route('matches');
    }

    /*
     * betting scoring
     */
    public function bettingScoring()
    {
        // date now
        date_default_timezone_set('America/Sao_Paulo');
        $date_now = date('Y-m-d H:i:s');

        $past_matches = DB::table( 'matches_soccers as ms' )
                            ->select(
                                'ms.id as match_id',
                                'ms.first_team_goals as mat_first_team_goals',
                                'ms.second_team_goals as mat_second_team_goals',
                                'bt.id as bet_id',
                                'bt.user_id as user_id',
                                // 'bt.matches_soccer_id',
                                'bt.first_team_goals as bet_first_team_goals',
                                'bt.second_team_goals as bet_second_team_goals',
                                'bt.score as bet_score'
                            )
                            ->join( 'bets as bt', 'ms.id', '=', 'bt.matches_soccer_id' )
                            ->where([
                                [ 'ms.match_date', '<', $date_now ],
                                [ 'bt.score', '=', null ]
                            ])
                            ->get();

        foreach ( $past_matches as $match ) {
            // reset score
            $score = 0;
            // 3 - exactly correct
            if (( $match->mat_first_team_goals == $match->bet_first_team_goals ) && ( $match->mat_second_team_goals == $match->bet_second_team_goals )) {
                $score = 3;
            }
            // 1 - tie situation
            elseif (( $match->mat_first_team_goals == $match->mat_second_team_goals ) && ( $match->bet_first_team_goals == $match->bet_second_team_goals )) {
                $score = 1;
            }
            // 1 - first team wins
            elseif (( $match->mat_first_team_goals > $match->mat_second_team_goals ) && ( $match->bet_first_team_goals > $match->bet_second_team_goals )) {
                $score = 1;
            }
            // 1 - second team wins
            elseif (( $match->mat_first_team_goals < $match->mat_second_team_goals ) && ( $match->bet_first_team_goals < $match->bet_second_team_goals )) {
                $score = 1;
            }
            // 0- lose
            else {
                $score = 0;
            }

            // update score
            DB::table( 'bets' )
                ->where( 'id', $match->bet_id )
                ->update([ 'score' => $score ]);
        }
        return;
    }
}
