<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use DB;

class HomeController extends Controller
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
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();
        // verify if wanna look create group
        if ( $user->view_create_group ) {
            return view( 'create_bet_group' );
        }
        else {
            /*
             * COLOCAR NA MESMA TELA OS JOGOS QUE FOREM ACONTECENDO
            */

            // betting scoring
            $this->bettingScoring();

            // verify exist user's bet groups
            $exist_bet_groups = DB::table( 'bets_groups' )
                                    ->select( 'name' )
                                    ->where( 'user_create_id', $user->id )
                                    ->exists();
            if ( $exist_bet_groups ) {
                // get user's bet groups
                $bet_groups = DB::table( 'bets_groups' )
                                ->select( 'name' )
                                ->where( 'user_create_id', $user->id )
                                ->get();
            }
            else {
                $bet_groups = null;
            }

            // get notifications
            $notifications = DB::table( 'invitations as inv' )
                                    ->join( 'bets_groups as btg', 'inv.bets_group_id', '=', 'btg.id' )
                                    ->where([
                                        [ 'inv.notify', '=', '1' ],
                                        [ 'btg.user_create_id', '=', $user->id ]
                                    ])
                                    ->count();

            // matches soccers
            $matches_soccers = DB::table( 'matches_soccers as mat' )
                                    ->select(
                                        'mat.id as match_id',
                                        'typ.id as type_id',
                                        'typ.name as type_name',
                                        'mat.first_team_id as team_a_id',
                                        'tea.url_flag as flag_a',
                                        'tea.nickname as team_a',
                                        'mat.first_team_goals as a_first_team_goals',
                                        'mat.second_team_id as team_b_id',
                                        'teb.url_flag as flag_b',
                                        'teb.nickname as team_b',
                                        'mat.second_team_goals as b_second_team_goals',
                                        DB::raw( 'date_format( mat.match_date, "%d/%m/%Y - %H:%i" ) as match_date' ),
                                        'bet.first_team_goals as bet_first_team_goals',
                                    	'bet.second_team_goals as bet_second_team_goals',
                                    	'bet.score as score'
                                    )
                                    ->join( 'type_matches as typ', 'mat.type_match_id', '=', 'typ.id' )
                                    ->join( 'teams as tea', 'mat.first_team_id', '=', 'tea.id' )
                                    ->join( 'teams as teb', 'mat.second_team_id', '=', 'teb.id' )
                                    ->leftJoin( 'bets as bet', function( $join ){
                                        $join->on( 'mat.id', '=', 'bet.matches_soccer_id' );
                                        $join->on( 'bet.user_id', '=', DB::raw( Auth::user()->id ) );
                                    })
                                    ->orderBy( 'mat.match_date', 'asc' )
                                    ->get();

            // get total score
            $total_score = DB::table( 'bets' )
                                ->where( 'user_id', '=', $user->id )
                                ->sum( 'score' );

            // update user total score
            if ( $total_score >= 0 ) {
                DB::table( 'users' )
                    ->where( 'id', $user->id )
                    ->update([ 'total_score' => $total_score ]);
            }

            return view( 'home' )->with([
                                    'bet_groups'        => $bet_groups,
                                    'matches_soccers'   => $matches_soccers,
                                    'total_score'       => $total_score,
                                    'notifications'     => $notifications
                                ]);
        }
    }

    /*
     * set fallse in view group
    */
    public function notViewCreateGroup()
    {
        // set false in view create group
        DB::table( 'users' )
            ->where( 'id', auth()->user()->id )
            ->update([ 'view_create_group' => false ]);

        return redirect()->route('home');
    }

    /*
     * betting scoring
     */
    public function bettingScoring()
    {
        $user = auth()->user();

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
                                [ 'bt.user_id', '=', $user->id ]
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
