<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;

class GroupController extends Controller
{
    public function __construct()
    {
        $this->middleware( 'auth' );
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // redirect the page create bet group
        return view( 'create_bet_group' );
    }

    /**
     * Create bets group
     *
     * @return \Illuminate\Http\Response
     */
    public function create( Request $request )
    {
        //
        $user = auth()->user();
        $name_bet_group = $request->input( 'namegroup' );

        $bet_group = DB::table( 'bets_groups' )
                        ->where([
                            [ 'user_create_id', '=', $user->id ],
                            [ 'name', '=', $name_bet_group ]
                        ])
                        ->exists();
        // if not exists
        if ( !$bet_group ) {
            // insert new group
            DB::table( 'bets_groups' )
                ->insert([
                    'user_create_id'    => $user->id,
                    'name'              => $name_bet_group
                ]);
            // relationship to betting groups created with the creator user
            $bet_group_id = DB::table( 'bets_groups' )
                                ->select( 'id' )
                                ->where([
                                    [ 'user_create_id', '=', $user->id ],
                                    [ 'name', '=', $name_bet_group ]
                                ])
                                ->get();
            // inserts relationship
            DB::table( 'user_bets_groups' )
                ->insert([
                    'user_id'       => $user->id,
                    'bets_group_id' => $bet_group_id[0]->id
                ]);
        }
        // select bet groups
        $bet_groups = DB::table( 'bets_groups' )
                        ->select( 'name' )
                        ->where( 'user_create_id', $user->id )
                        ->get();

        return view( 'home' )->with( 'bet_groups', $bet_groups );
    }

    /**
     * search bets group
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function search( $name )
    {
        // get bet group searched
        $response = DB::table( 'bets_groups' )
                        ->select( 'name' )
                        ->where( 'name', 'LIKE', '%'.$name.'%' )
                        ->paginate( 20 );

        return response()->json([
            'groups' => $response
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}