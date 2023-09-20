@php

$accounts = \App\Models\TradeAccount::where('authorised', true)->get();
$users = \App\Models\User::where('authorised', true)->get();

@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Hierarchy view component</title>

    <link rel="stylesheet" href="{{url('/hierarchy-view/hierarchy-view.css')}}">
    <link rel="stylesheet" href="{{url('/hierarchy-view/main.css')}}">
    <script src="https://code.jquery.com/jquery-3.6.0.js" integrity="sha256-H+K7U5CnXl1h5ywQfKtSj8PCmoN9aaq30gDh27Xc0jk=" crossorigin="anonymous"></script>
    <style type="text/css">
        label{
            font-size: 10px;
            float: left;
        }

        input, select{
            float: right;
            font-size: 11px;
        }

        form{
            margin: 0;
            background-color: #fff;
            color: #DE5454;
            padding: 30px;
            border-radius: 7px;
            min-width: 100px;
            text-align: center;
            box-shadow: 0 3px 6px rgb(204 131 103 / 22%);
            display: block;
            /*margin-block-start: 1em;*/
            /*margin-block-end: 1em;*/
            margin-inline-start: 0px;
            margin-inline-end: 0px;
        }

        formheader{
            display: block;
            font-size: 10px;
            margin-block-start: -1em;
            margin-block-end: 1em;
            margin-inline-start: 0px;
            margin-inline-end: 0px;
            font-weight: bold;
            word-wrap: break-word;
            width: 210px;
        }

        .navbar {
          overflow: hidden;
          background-color: #333;
          position: fixed;
          bottom: 0;
          width: 100%;
          height: 62px;
        }

        .navbar button {
          float: left;
          display: block;
          color: #f2f2f2;
          text-align: center;
          padding: 11px 8px;
          text-decoration: none;
          font-size: 17px;
        }

        .navbar button:hover {
          background: #f1f1f1;
          color: black;
        }

        .navbar button.active {
          background-color: #04AA6D;
          color: white;
        }

        p.simple-card{
            padding-bottom: 76px;
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style type="text/css">
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #e4e4e4;
            border: 1px solid #aaa;
            border-radius: 4px;
            cursor: default;
            float: left;
            margin-right: 5px;
            margin-top: 5px;
            padding: 0 5px;
            font-size: 8px;
        }
    </style>
</head>

<body onBlur="window.focus()">
    <form action="/treeMapper" method="post">
        @csrf
        @method('POST')
    <!--Basic style-->    
    <section class="basic-style">
        <h1>Account Mapping Tree</h1>
        <div class="hv-container">
            <div class="hv-wrapper">

                @if(isset($mappingArray))
                    @foreach($mappingArray as $index => $parent)
                    <!-- Key component -->
                    @php
                        $readonlyFlag = 'readonly="false"';
                        if(isset($parent['nested']))
                            $readonlyFlag = 'readonly="true"';

                    @endphp
                    <div class="hv-item">
                        <div class="hv-item-parent">
                            <p class="simple-card">
                                <formheader>Parent</formheader>

                                @if(isset($parent['setDisabled']))
                                <label>Disable Account/User</label>
                                <input type="checkbox" name="{{$index.'_setDisabled'}}" {{(isset($parent['setDisabled']) && $parent['setDisabled']==='on')?'checked':''}} readonly="true"> <br>
                                @if(isset($parent['mappedUserAction']))
                                <label>Sub User Action</label>
                                <select name="{{$index.'_mappedUserAction'}}" readonly="true">
                                    <option value="da" {{($parent['mappedUserAction']==='da')?'selected':''}}>Disable All</option>
                                    <option value="mta" {{($parent['mappedUserAction']==='mta')?'selected':''}}>Map to Account</option>
                                    <option value="mi" {{($parent['mappedUserAction']==='mi')?'selected':''}}>Map Individually</option>
                                </select><br>
                                @endif
                                @endif
                                
                                <label>Mapping Type</label>
                                <select name="{{$index.'_role'}}">
                                    <option value="{{$parent['role']}}">{{ucwords($parent['role'])}}</option>
                                </select> <br>

                                <label>Effective From</label>
                                <input type="date" name="{{$index.'_startdate'}}" value="{{Carbon\Carbon::parse($parent['startdate'])->toDateString()}}" readonly="true"><br>
                                
                                @if(isset($parent['groupid']))
                                <label>Account</label>
                                <select name="{{$index.'_groupid'}}">
                                    @foreach($accounts as $group)
                                        @if($parent['groupid']===$group->accountid)
                                    <option value="{{$group->accountid}}" {{(($parent['groupid']===$group->accountid)?'selected':'')}}>{{$group->accountid}}</option>
                                        @endif
                                    @endforeach
                                </select><br>
                                @endif
                                @if(isset($parent['newmems']))
                                <label>Select Traders : </label><br>
                                <label style="float: right;">
                                    {{implode(', ', \App\Models\User::whereIn('_id', $parent['newmems'])->get()->pluck('memberId')->toArray())}}
                                </label>
                                <fg style="display: none;">
                                    <select name="{{$index.'_newmems[]'}}" class="select2" style="width: 182px;" multiple readonly>
                                        @foreach($users as $user)
                                            @if(sizeof($parent['newmems'])>0 && in_array($user->_id, $parent['newmems']))
                                            <option value="{{$user->_id}}" {{sizeof($parent['newmems'])>0?((in_array($user->_id, $parent['newmems']))?'selected':''):''}}>{{$user->memberId}}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    
                                </fg>
                                @endif
                            </p>
                        </div>
                        @if(isset($parent['nested']))
                        <div class="hv-item-children">
                            @include('node', ['node' => $parent['nested'], 'accounts' => $accounts, 'users' => $users, 'position' => $index])
                        </div>
                        @endif
                    </div>
                    @endforeach
                @endif

            </div>
    <br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
        </div>
    </section>


    <div class="navbar">
        <div style="display: flex;justify-content: center;padding: 8px;">
            <button type="submit" style="background-color: green;">Submit</button>
            <button style="background-color: red;margin-left: 50px;" onclick="window.location.href='/greetings'">Cancel</button>
        </div>
    </div>
    </form>

    
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script type="text/javascript">
        var trDetails = @json($trDetails);
        $(function () {
            $(document).ready(function() {
                $('.select2').select2({
                  tags: true,
                  // dropdownParent: $('.form-horizontal')
                });
            });

            $('.accountDisabler').change(function () {
                $(this).siblings('.mappedUserAction').prop('disabled', !$(this).is(':checked'));
                $(this).siblings('.mappedUserAction').prop('required', $(this).is(':checked'));

                $(this).siblings('fg').children('.accountSelector').prop('required', !$(this).is(':checked'));
                $(this).siblings('fg').children('.userSelector').prop('required', !$(this).is(':checked'));

                $(this).siblings('.disablePortion').toggle();
                
            });


            $('.accountSelector').change(function(){
                let arr = [
                    $(this).siblings('.startdateSelector').val(),
                    $(this).siblings('.roleSelector').val(),
                    $(this).val(),
                    $(this).siblings('.userSelector').val(),
                ]

                let flag = validateSelection(arr);
                // console.log($('.accountSelector').val());
                if(!flag){
                    $(this).prop('selectedIndex', 0);
                }
            });

            $('.userSelector').change(function(){
                let arr = [
                    $(this).siblings('.startdateSelector').val(),
                    $(this).siblings('.roleSelector').val(),
                    $(this).siblings('.accountSelector').val(),
                    $(this).val(),
                ]

                let flag = validateSelection(arr);
                // console.log($('.accountSelector').val());
                if(!flag){
                    $(this).prop('selectedIndex', 0);
                }
            });

            $('.roleSelector').change(function(){
                let arr = [
                    $(this).siblings('.startdateSelector').val(),
                    $(this).val(),
                    $(this).siblings('.accountSelector').val(),
                    $(this).siblings('.userSelector').val(),
                ]

                let flag = validateSelection(arr);
                // console.log($('.accountSelector').val());
                if(!flag){
                    $(this).prop('selectedIndex', 0);
                }
            });

      // $('.userSelector').select2();
      // $('.userSelectorMul').select2("readonly", true);

        });
        $('.blocklist').change(function(){
            $(this).siblings('fg').children('.accountSelector').prop('required', !$(this).is(':checked'));
            $(this).siblings('fg').children('.userSelector').prop('required', !$(this).is(':checked'));
        
            $(this).siblings('.disablePortion').toggle();
        });


        function validateSelection(input){
            let flag = true;
            trDetails.forEach((element, index)=>{
                if(element['mappedTo'] == input[2] && element['role'] == input[1]){
                    flag = false;
                    alert('You cannot Assign a user as Master for an Account twice!');
                }



            });
            return flag;
        }
    </script>

</body>

</html>