<?php

namespace App\Http\Controllers;

use App\Loan;
use App\Client;
use App\Payment;
use Carbon\Carbon;
use App\Mail\AgapeEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LoanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $loans = Loan::with('client','payment')->Orderby('created_at','desc')->get();
        $counter = $loans->count();
        return view('loan.index', [
            'loans' => $loans,
            'counter' => $counter,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $clients = Client::where(function ($query) {
            $query->whereIn('status',['out of tenure', '0']);
        })->get();
        return view('loan.create', compact('clients'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|int',
            'loan_amount' => 'required|numeric|min:0',
            'tenure' => 'required|string|min:1',
            // 'intrest_percent' => 'required|string|min:1',
            'formpayment' => 'required|int|min:0',
            'forward_payment' => 'required|numeric|min:1',
        ]);
        {
        $loan_amount = $request->loan_amount;
        $intrest = $request->intrest_percent*$request->tenure /100 * $loan_amount;
        $cal_payback = $intrest + $loan_amount;
        $monthly_payback = ceil($cal_payback / $request->tenure);
        $total_payback = $monthly_payback * $request->tenure;
        $fp_amount = ceil($request->forward_payment/100 * $loan_amount + 1000);
        $profit = $intrest + $fp_amount;
        $intrest_permonth = $intrest / $request->tenure;

        $Loan = Loan::create([
            'client_id' => $request->client_id,
            'loan_amount' => $request->loan_amount,
            'tenure'=>$request->tenure,
            'intrest'=> $intrest,
            'actual_profit'=>$request->formpayment,
            'intrest_percent'=> $request->intrest_percent,
            'monthly_payback' => $monthly_payback,
            'total_payback'=> $total_payback,
            'fp_amount'=>$fp_amount,
            'forward_payment'=>$request->forward_payment,
            'fp_status'=>'Not paid',
            'formpayment' =>$request->formpayment,
            'monthly_profit' =>$request->formpayment,
            'yearly_profit' =>$request->formpayment,
            'purpose'=>'loan',
            'admin_incharge' => Auth()->user()->name,

        ]);
        $client = Client::whereId($request->client_id)->first();
        $client->status= 'in review';
        $client->save();
        
        $data = [
            'client_no'=> $client->client_no,
            'name'=> $client->name,
            'phone'=> $client->phone,
            'loan_amount' => $request->loan_amount,
            'intrest_percent' => $request->intrest_percent,
            'formpayment' => $request->formpayment,
            'tenure'=>$request->tenure,
            'intrest'=> $intrest,
            'monthly_payback' => $monthly_payback,
            'total_payback'=> $total_payback,
            'fp_amount'=>$fp_amount,
            'subject'=> 'New Loan Request',
            'type'=> 'loan request',
            'admin_incharge'=> Auth()->user()->name,
            'date'=> Carbon::now(),
        ];
        //Mail::to('theconsode@gmail.com')->send(new AgapeEmail($data));
        //Mail::to('info@agapeglobal.com.ng')->send(new AgapeEmail($data));
        return redirect(route('loan'))->with('message', 'Loan Request Sent');
    
    
        }
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
        $loan = Loan::with('client','payment')->where('id', $id)->first();
        return view('loan.edit',compact('loan'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function disburse(Request $request)
    {
        
        $loan = Loan::with('client','payment')->whereId($request->loan_id)->first();
        if(date('d,M Y', strtotime($request->disbursement_date)) > date('d,M Y')){
            return back()->with('error', 'Disbursement Date cannot be greater than present Date');
        }
        if($request['disbursement_date']){
            $loan->disbursement_date = $request->disbursement_date;
        }else{
        $loan->disbursement_date = Carbon::now();
        }
        $loan->fp_status = (is_null($request->fp_status) ? 'Not paid' : 'Paid' );
        $now = date('M, Y', strtotime(Carbon::parse($loan->disbursement_date)->addDay(30)));
        $then = Carbon::parse($loan->disbursement_date)->addMonth($loan->tenure);
        $loan->loan_duration = $now. ' to ' .$then->format('M, Y');
        $loan->expected_profit = $loan->intrest + $loan->fp_amount + $loan->formpayment;
        $loan->sum_of_allpayback = 0;
        $loan->status= 1;
        $loan->actual_profit = $loan->fp_amount + $loan->formpayment;
        if($loan->updated_at->format('m,Y') == date('m,Y')){
        $loan->monthly_profit = $loan->monthly_profit + $loan->fp_amount;
        }else{
            $loan->monthly_profit = $loan->fp_amount;
        }
        if($loan->updated_at->format('Y') == date('Y')){
            $loan->yearly_profit = $loan->yearly_profit + $loan->fp_amount;
            }else{
                $loan->yearly_profit = $loan->fp_amount;
            }
        $loan->client->status= 'in tenure';
        $loan->admin_who_disburse = Auth()->user()->name;
        $loan->save();
        $loan->client->save();

         
        $duedate = Carbon::parse($loan->disbursement_date);
        $nextduedate = $duedate->addDay(30);
        Payment::create([
            'client_id' => $loan->client_id,
            'loan_id' => $loan->id,
            'next_due_date' => $nextduedate,
            'outstanding_payment' => $loan->total_payback,
            'expect_pay' => $loan->monthly_payback,
            'bb_forward' => 0.00,
            'payback_permonth' => $loan->monthly_payback,
            'payment_status' => 0,
        ]);
        
        $payment = Payment::with('client','loan')->where('loan_id',$request->loan_id)->where('payment_status',0)->first();
        $data = [
            'client_no'=> $loan->client->client_no,
            'name'=> $loan->client->name,
            'phone'=> $loan->client->phone,
            'loan_amount' => $loan->loan_amount,
            'total_payback' => $loan->total_payback,
           'tenure'=>$loan->tenure,
           'intrest'=> $loan->intrest,
            'monthly_payback' => $payment->payback_permonth,
            'outstanding'=> $payment->outstanding_payment,
            'fp_amount'=>$loan->fp_amount,
            'fp_status'=>$loan->fp_status,
            'expect_profit'=>$loan->expected_profit,
            'next_due_date'=> $nextduedate,
           'expect_pay' => $payment->expect_pay,
           'subject'=> 'Loan Disbursed',
           'type'=> 'loan disbursement',
            'disbursement_date' => $loan->disbursement_date,
            'loan_duration' => $loan->loan_duration,
            'admin_incharge'=> Auth()->user()->name,
            'date'=> Carbon::now(),
        ];
       // Mail::to('info@agapeglobal.com.ng')->send(new AgapeEmail($data));
       // Mail::to('theconsode@gmail.com')->send(new AgapeEmail($data));
        return redirect(route('loan'))->with('message', 'Loan Disbursed');
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
        $request->validate([
            'client_id' => 'required|int',
            'loan_amount' => 'required|numeric|min:10',
            'tenure' => 'required|string|max:10',
            'intrest_percent' => 'required|string|min:1',
            'forward_payment' => 'required|numeric|min:1',
        ]);
        {
            $loan_amount = $request->loan_amount;
            $intrest = $request->intrest_percent*$request->tenure /100 * $loan_amount;
            $cal_payback = $intrest + $loan_amount;
            $monthly_payback = ceil($cal_payback / $request->tenure);
            $total_payback = $monthly_payback * $request->tenure;
            $fp_amount = ceil($request->forward_payment/100 * $loan_amount + 1000);
            $profit = $intrest + $fp_amount;
            $intrest_permonth = $intrest / $request->tenure;

        $loan= Loan::find($id);
        $loan->loan_amount=$request->loan_amount;
        $loan->tenure=$request->tenure;
        $loan->intrest=$intrest;
        $loan->intrest_percent=$request->intrest_percent;
        $loan->monthly_payback=$monthly_payback;
        $loan->total_payback=$total_payback;
        $loan->fp_amount=$fp_amount;
        $loan->forward_payment=$request->forward_payment;
        $loan->save();
        
        $data = [
            'client_no'=> $loan->client->client_no,
            'name'=> $loan->client->name,
            'phone'=> $loan->client->phone,
            'loan_amount' => $request->loan_amount,
            'tenure'=>$request->tenure,
            'intrest'=> $intrest,
            'intrest_percent' => $request->intrest_percent,
            'formpayment' => $request->formpayment,
            'monthly_payback' => $monthly_payback,
            'total_payback'=> $total_payback,
            'fp_amount'=>$fp_amount,
            'subject'=> 'Loan Request Updated',
            'type'=> 'update loan request',
            'admin_incharge'=> Auth()->user()->name,
            'date'=> Carbon::now(),
        ];
        //Mail::to('theconsode@gmail.com')->send(new AgapeEmail($data));
        //Mail::to('info@agapeglobal.com.ng')->send(new AgapeEmail($data));

        return redirect(route('loan'))->with('message', 'Loan Request Updated');
    
    
        }
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