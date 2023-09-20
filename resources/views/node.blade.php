@foreach($node as $nodeKey => $data)
	@if(isset($data['nested']))
		<div class="hv-item-child">
            <!-- Key component -->
            <div class="hv-item">
                <div class="hv-item-parent">
                    <p class="simple-card">
                		<formheader>{{$data['triggerFor']}}</formheader>
                		<input type="hidden" name="{{$position.'_nested_'.$nodeKey.'_triggerFor'}}" value="{{$data['triggerFor']}}">
                		<input type="hidden" name="{{$position.'_nested_'.$nodeKey.'_trFor'}}" value="{{$data['trFor']}}">
	                    @if(isset($data['setDisabled']))
	                    <label>Disable Account/User</label>
            			<input type="checkbox" name="{{$position.'_nested_'.$nodeKey.'_setDisabled'}}" {{(isset($data['setDisabled']) && $data['setDisabled']==='on')?'checked':''}} readonly="true"> <br>
            			@if($data['groupid']!='' && $data['groupid']!=null)
            			<label>Sub User Action</label>
		    			<select name="{{$position.'_nested_'.$nodeKey.'_mappedUserAction'}}" readonly="true">
		    				<option value="da" {{($data['mappedUserAction']==='da')?'selected':''}}>Disable All</option>
		    				<option value="mta" {{($data['mappedUserAction']==='mta')?'selected':''}}>Map to Account</option>
		    				<option value="mi" {{($data['mappedUserAction']==='mi')?'selected':''}}>Map Individually</option>
		    			</select><br>
	                    @endif
	                    @endif
	                    <label>Effective From</label>
	                    <input type="date" name="{{$position.'_nested_'.$nodeKey.'_startdate'}}" value="{{Carbon\Carbon::parse($data['startdate'])->toDateString()}}" readonly="true"><br>
	                    
	                    <label>Account</label>
		                <select name="{{$position.'_nested_'.$nodeKey.'_groupid'}}">
		                	@foreach($accounts as $group)
			                	@if($data['groupid']===$group->accountid)
		                    <option value="{{$group->accountid}}" {{(($data['groupid']===$group->accountid)?'selected':'')}}>{{$group->accountid}}</option>
			                	@endif
		                    @endforeach
		                </select><br>
		                @if(isset($data['newmems']))
		                <label>Select Traders</label>

		                <label>
		                	{{implode(', ', \App\Models\User::whereIn('_id', $data['newmems'])->get()->pluck('memberId')->toArray())}}
		                </label>
		                <select name="{{$position.'_nested_'.$nodeKey.'_newmems[]'}}" {{sizeof($data['newmems'])>1?'multiple':''}} style="display:none;">
		                	@foreach($users as $user)
			                	@if(in_array($user->_id, $data['newmems']))
			                    <option value="{{$user->_id}}" {{sizeof($data['newmems'])>0?((in_array($user->_id, $data['newmems']))?'selected':''):''}}>{{$user->memberId}}</option>
			                	@endif
		                    @endforeach
		                </select><br>
		                @endif
	                    
	                    <label>Mapping Type</label>
	                    <select name="{{$position.'_nested_'.$nodeKey.'_role'}}">
	                        <option value="{{$data['role']}}">{{ucwords($data['role'])}}</option>
	                    </select> 
	                </p>
                </div>
                <div class="hv-item-children">
					@include('node', ['node' => $data['nested'], 'accounts' => $accounts, 'users' => $users, 'position' => $position.'_nested_'.$nodeKey])
				</div>
			</div>
		</div>
	@else

		<div class="hv-item-child">
            <p class="simple-card">
                <formheader>{{$data['triggerFor']}}</formheader>    
                <input type="hidden" name="{{$position.'_nested_'.$nodeKey.'_triggerFor'}}" value="{{$data['triggerFor']}}">
                <input type="hidden" name="{{$position.'_nested_'.$nodeKey.'_trFor'}}" value="{{$data['trFor']}}">
                @if(isset($data['setDisabled']))
                @if($data['groupid']!='' && $data['groupid']!=null)
                <label>Disable Account</label>
    			<input class="accountDisabler" type="checkbox" name="{{$position.'_nested_'.$nodeKey.'_setDisabled'}}" {{(isset($data['setDisabled']) && $data['setDisabled']==='on')?'checked':''}} readonly="true"> <br>

                <label>Sub User Action</label>
    			<select class="mappedUserAction" name="{{$position.'_nested_'.$nodeKey.'_mappedUserAction'}}" disabled="true">
    				<option value="da">Disable All</option>
    				<option value="mta">Map to Account</option>
    				<option value="mi">Map Individually</option>
    			</select><br>
				@else
                <label>Disable User</label>
    			<input type="checkbox" class="blocklist" name="{{$position.'_nested_'.$nodeKey.'_setDisabled'}}" {{(isset($data['setDisabled']) && $data['setDisabled']==='on')?'checked':''}}> <br>
                @endif
            	
            	@endif
            	<fg class="disablePortion">
	            	<label>Effective From</label>
	                <input type="date" name="{{$position.'_nested_'.$nodeKey.'_startdate'}}" {{($data['startdate']!=''?'readonly="true"':'')}} value="{{Carbon\Carbon::parse($data['startdate'])->toDateString()}}" class="startdateSelector"><br>

	                <label>Account</label>
	                <select class="accountSelector" name="{{$position.'_nested_'.$nodeKey.'_groupid'}}"  {{(isset($data['setDisabled']) && $data['setDisabled']==='on')?'':'required'}}>
	                	@if($data['groupid']==='' || $data['groupid']==null)
	                	<option value="">-Select-</option>
	                	@endif
	                	@foreach($accounts as $group)
	                		@if($data['groupid']!='')
	                			@if($data['groupid']===$group->accountid)
	                    <option value="{{$group->accountid}}" {{(($data['groupid']===$group->accountid)?'selected':'')}}>{{$group->accountid}}</option>@endif
	                    	@else
	                    <option value="{{$group->accountid}}" {{(($data['groupid']===$group->accountid)?'selected':'')}}>{{$group->accountid}}</option>
	                    	@endif
	                    @endforeach
	                </select><br>
	                
	                <label>Select Traders</label>
	                <select class="userSelector" name="{{$position.'_nested_'.$nodeKey.'_newmems[]'}}" {{(isset($data['setDisabled']) && $data['setDisabled']==='on')?'':'required'}}  {{sizeof($data['newmems'])>1?'multiple':''}} style="{{($data['trFor']==='user' && sizeof($data['newmems'])>1)?'display:none;':''}}">
	                	@if(sizeof($data['newmems'])==0)
	                		<option value="">-Select-</option>
	                	@endif
	                	@foreach($users as $user)

	                	@if(sizeof($data['newmems'])>0)

	                		@if(in_array($user->_id, $data['newmems']))
	                    <option value="{{$user->_id}}" {{sizeof($data['newmems'])>0?((in_array($user->_id, $data['newmems']))?'selected':''):''}}>{{$user->memberId}}</option>
	                    	@endif
	                    @else
	                    <option value="{{$user->_id}}" {{sizeof($data['newmems'])>0?((in_array($user->_id, $data['newmems']))?'selected':''):''}}>{{$user->memberId}}</option>
	                    @endif
	                    @endforeach
	                </select>
	                @if($data['trFor']==='user' && sizeof($data['newmems'])>1)
	                <Label>{{':  '.implode(', ', \App\Models\User::whereIn('_id', $data['newmems'])->get()->pluck('memberId')->toArray())}}</label>
	                @endif
	                <br>
	                
	                <label>Mapping Type</label>
	                <select name="{{$position.'_nested_'.$nodeKey.'_role'}}" class="roleSelector">
	                	@if($data['role']==='')
	                    <option value="sub" {{($data['role']==='sub'?'selected':'')}}>Sub</option>
	                    <option value="master" {{($data['role']==='master'?'selected':'')}}>Master</option>
	                    @else
	                    <option value="{{$data['role']}}">{{ucwords($data['role'])}}</option>
	                    @endif
	                </select>
                </fg> 
            </p>
        </div>
	@endif
@endforeach