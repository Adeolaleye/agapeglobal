<?php

namespace App\Http\Controllers;

use App\Loan;
use App\Client;
use App\Payment;
use Carbon\Carbon;
use App\Mail\AgapeEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use phpDocumentor\Reflection\Types\Null_;

class InTenureController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
// Start of intenure view 
    public function index()
    {
        $loans = Loan::with('client','payment')->whereIn('status',['1','3'])->orderBy('updated_at', 'asc')->get();
        $counter =$loans->count();
        return view('intenure.index', [
            'loans' => $loans,
            'counter' => $counter,
        ]); 
    }
// End of intenure view 
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
// Start of all makepayment view
    public function makepayment(Request $request, $id)
    {
        //dd($id);
        $loan = Loan::with('client','payment')->where('id', $id)->first();
        // $payment = $loan->payment->payment_status->first();
        $paymentimes = Payment::where('loan_id',$id)->where('payment_status',1)->count();
        $unpaiddetails = Payment::where('loan_id',$id)->where('payment_status',0)->first(); 
        return view('intenure.payback', [
            'loan' => $loan,
            'paymentimes' => $paymentimes,
            'unpaiddetails' => $unpaiddetails,
        ]);
    }
// End of all makepayment view
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

// Start of partial payment function
    public function partialpay(Request $request, $id)
    {
        $request->validate([
            'amount_paid' => 'required|int|min:0',
        ]);
        $payment = Payment::with('client','loan')->where('loan_id',$id)->where('payment_status',0)->first();
        if(($request->amount_paid) > $payment->expect_pay){
            return back()->with('warning', 'Client cannot pay greater than expected pay as partial pay');
        }
        if(($request->amount_paid) == $payment->expect_pay){
            return back()->with('error', 'Use direct make payment option instead to complete payment for the month');
        }
        $payment->amount_paid= $payment->amount_paid + $request->amount_paid;
        $payment->partial_pay= $payment->partial_pay + $request->amount_paid;
        $payment->date_paid = Carbon::now();
        $payment->payment_purpose = 'partial payment';
        $payment->outstanding_payment = $payment->outstanding_payment - $request->amount_paid;
        $payment->expect_pay = $payment->expect_pay - $request->amount_paid;
        if($payment->loan->updated_at->format('m,Y') <> date('m,Y')){
        $payment->loan->monthly_profit = 0;
        }
        $payment->loan->sum_of_allpayback = $payment->loan->sum_of_allpayback + $request->amount_paid;
        $payment->save();
        $payment->loan->save();

        $data = [
            'client_no'=> $payment->client->client_no,
            'name'=> $payment->client->name,
            'phone'=> $payment->client->phone,
            'total_payback'=> $payment->loan->total_payback,
            'loan_amount' => $payment->loan->loan_amount,
            'amount_paid' => $request->amount_paid,
            'next_pay' => $payment->expect_pay,
            'partial_pay' => $payment->partial_pay,
            'total_amountpaid' => $payment->loan->sum_of_allpayback,
            'monthly_payback' => $payment->payback_permonth,
            'outstanding' => $payment->outstanding_payment,
            'next_due_date' => $payment->next_due_date,
            'subject'=> 'Partial Payment Made',
            'type'=> 'partial payment',
            'date_paid' => $payment->date_paid,
            'admin_incharge'=> Auth()->user()->name,
            'date'=> Carbon::now(),

        ];
        //Mail::to('info@agapeglobal.com.ng')->send(new AgapeEmail($data));
        //Mail::to('theconsode@gmail.com')->send(new AgapeEmail($data));
        return back()->with('message', 'Partial Payment Made Successfully!');
    }
// End of partial payment function

// Start of all payback function
    public function paynow(Request $request, $id)
    {
        $request->validate([
            'amount_paid' => 'required|int|min:0',
        ]);
        $paymentdetails = Payment::with('client','loan')->where('loan_id',$id)->where('payment_status','0');
       
        $paymentdetail = Payment::with('client','loan')->where('loan_id',$id);
        $payment = $paymentdetails->first();
        $payments = $paymentdetails->get();
        $paymentcount = $paymentdetail->count();      
        $bb_forward = $payment->expect_pay - $request->amount_paid;

        // Start of condition for paying too much
        if(($paymentdetail->sum('amount_paid') + $request->amount_paid) > $payment->loan->total_payback){
            return back()->with('error', 'Client cannot pay above expected amount');
        }
        // End of condition for paying too much

        // Start of Condition for payment made but does not equivalent to payback, Tenure Extended
        if($payment->loan->tenure == $paymentcount && ($paymentdetail->sum('amount_paid') + $request->amount_paid < $payment->loan->total_payback) or $payment->client->status == 'tenure extended' && ($paymentdetail->sum('amount_paid') + $request->amount_paid < $payment->loan->total_payback)){
            $intrest_permonth = $payment->loan->intrest / $payment->loan->tenure;
            if($request->amount_paid + $payment->partial_pay < $intrest_permonth && $payment->client->status == 'in tenure' ){
                return back()->with('error', 'Payment is too low for the month');
            }
                $duedate = Carbon::parse($payment->next_due_date);
                $nextduedate = $duedate->addDay(31);
                $payment->amount_paid= $payment->amount_paid + $request->amount_paid;
                $payment->date_paid = Carbon::now();
                $payment->payment_purpose = 'loan payback';
                $payment->payment_status = 1;
                $payment->admin_incharge = Auth()->user()->name;
                $payment->loan->status = 3;
                // $payment->loan->sum_of_allpayback = $paymentdetail->sum('amount_paid') + $request->amount_paid;
                $payment->loan->sum_of_allpayback = $payment->loan->sum_of_allpayback + $request->amount_paid;
            if($payment->client->status == 'in tenure' ){
                $payment->loan->actual_profit = $payment->loan->actual_profit + $intrest_permonth;
                $payment->profit = $intrest_permonth;
            }
            if($payment->loan->updated_at->format('m,Y') == date('m,Y') && $payment->client->status == 'in tenure'){
                $payment->loan->monthly_profit = $payment->loan->monthly_profit + $intrest_permonth;
                }
            elseif($payment->client->status == 'in tenure'){
                    $payment->loan->monthly_profit = $intrest_permonth;
                }
            if($payment->loan->updated_at->format('Y') == date('Y')){
                $payment->loan->yearly_profit = $payment->loan->yearly_profit + $intrest_permonth;
                }
            else{
                    $payment->loan->yearly_profit = $intrest_permonth;
                }
                $payment->client->status= 'tenure extended';
                $payment->save();
                $payment->loan->save();
                $payment->client->save();

            Payment::create([
                'client_id' => $request->client_id,
                'loan_id' => $id,
                'next_due_date' => $nextduedate,
                'outstanding_payment' => $payment->outstanding_payment - $request->amount_paid,
                'expect_pay' => $bb_forward,
                'bb_forward' => $bb_forward,
                'payback_permonth' => $payment->payback_permonth,
                'payment_status' => 0,
            ]);

            $paymentimes = Payment::where('loan_id',$id)->where('payment_status',1)->count();
            $data = [
                'client_no'=> $payment->client->client_no,
                'name'=> $payment->client->name,
                'phone'=> $payment->client->phone,
                'total_payback'=> $payment->loan->total_payback,
                'tenure'=> $payment->loan->tenure,
                'loan_amount' => $payment->loan->loan_amount,
                'loan_duration' => $payment->loan->loan_duration,
                'intrest' => $payment->loan->intrest, 
                'disbursement_date' => $payment->loan->disbursement_date, 
                'total_amountpaid' => $payment->loan->sum_of_allpayback,
                'actual_profit' => $payment->loan->actual_profit,
                'amount_paid' => $request->amount_paid,
                'next_pay' => $payment->expect_pay,
                'monthly_payback' => $payment->payback_permonth,
                'no_of_time_paid' => $paymentimes,
                'outstanding' => $payment->outstanding_payment,
                'bb_forward' => $payment->bb_forward,
                'next_due_date' => $nextduedate,
                'subject'=> 'New Payment made, but Tenure Extended',
                'type'=> 'payment extended',
                'date_paid' => $payment->date_paid,
                'admin_incharge'=> Auth()->user()->name,
                'date'=> Carbon::now(),

            ];
            //Mail::to('theconsode@gmail.com')->send(new AgapeEmail($data));
            //Mail::to('info@agapeglobal.com.ng')->send(new AgapeEmail($data));
            return back()->with('message', 'Payment Made Successfully, But Payback not completed, Tenure Extended!');
        }
        // End of Condition for payment made but does not equivalent to payback, Tenure Extended

        // Start of condition for payment, still In Tenure
        if($paymentdetail->sum('amount_paid') + $request->amount_paid < $payment->loan->total_payback){
            $intrest_permonth = $payment->loan->intrest / $payment->loan->tenure;
            if($request->amount_paid + $payment->partial_pay < $intrest_permonth && $payment->client->status == 'in tenure' ){
                return back()->with('error', 'Payment is too low for the month');
            }
                $duedate = Carbon::parse($payment->next_due_date);
                $nextduedate = $duedate->addDay(31);
                $payment->amount_paid= $payment->amount_paid + $request->amount_paid;
                $payment->date_paid = Carbon::now();
                $payment->payment_purpose = 'loan payback';
                $payment->payment_status = 1;
                $payment->admin_incharge = Auth()->user()->name;
                $payment->loan->sum_of_allpayback = $payment->loan->sum_of_allpayback + $request->amount_paid;
            if($payment->client->status == 'in tenure' ){
                $payment->loan->actual_profit = $payment->loan->actual_profit + $intrest_permonth;
                $payment->profit = $intrest_permonth;
            }
            if($payment->loan->updated_at->format('m,Y') == date('m,Y')){
                $payment->loan->monthly_profit = $payment->loan->monthly_profit + $intrest_permonth;
                }
            else{
                $payment->loan->monthly_profit = $intrest_permonth;
                }
            if($payment->loan->updated_at->format('Y') == date('Y')){
                $payment->loan->yearly_profit = $payment->loan->yearly_profit + $intrest_permonth;
                }
            else{
                    $payment->loan->yearly_profit = $intrest_permonth;
                }
                $payment->save();
                $payment->loan->save();
          
            Payment::create([
                'client_id' => $request->client_id,
                'loan_id' => $id,
                'next_due_date' => $nextduedate,
                'outstanding_payment' => $payment->outstanding_payment - $request->amount_paid,
                'expect_pay' => $payment->loan->monthly_payback + $bb_forward,
                'bb_forward' => $bb_forward,
                'payback_permonth' => $payment->payback_permonth,
                'payment_status' => 0,
            ]);
            $paymentimes = Payment::where('loan_id',$id)->where('payment_status',1)->count();
            $data = [
                'client_no'=> $payment->client->client_no,
                'name'=> $payment->client->name,
                'phone'=> $payment->client->phone,
                'total_payback'=> $payment->loan->total_payback,
                'tenure'=> $payment->loan->tenure,
                'loan_amount' => $payment->loan->loan_amount,
                'loan_duration' => $payment->loan->loan_duration,
                'intrest' => $payment->loan->intrest, 
                'disbursement_date' => $payment->loan->disbursement_date, 
                'total_amountpaid' => $payment->loan->sum_of_allpayback,
                'actual_profit' => $payment->loan->actual_profit,
                'amount_paid' => $request->amount_paid,
                'next_pay' => $payment->expect_pay,
                'monthly_payback' => $payment->payback_permonth,
                'no_of_time_paid' => $paymentimes,
                'outstanding' => $payment->outstanding_payment,
                'bb_forward' => $payment->bb_forward,
                'next_due_date' => $nextduedate,
                'subject'=> 'New Payment made',
                'type'=> 'payment',
                'date_paid' => $payment->date_paid,
                'admin_incharge'=> Auth()->user()->name,
                'date'=> Carbon::now(),

            ];
            //Mail::to('info@agapeglobal.com.ng')->send(new AgapeEmail($data));
            //Mail::to('theconsode@gmail.com')->send(new AgapeEmail($data));
            return back()->with('message', 'Payment Made Successfully');
        }
        // End of condition for payment, still In Tenure

        // Start of condition for completing payback, Out of Tenure
        if($paymentdetail->sum('amount_paid') + $request->amount_paid == $payment->loan->total_payback){
            $intrest_permonth = $payment->loan->intrest / $payment->loan->tenure;
            if($request->amount_paid + $payment->partial_pay < $intrest_permonth && $request->amount_paid + $payment->partial_pay <> $payment->outstanding_payment && $payment->client->status == 'in tenure' ){
                return back()->with('error', 'Payment is too low for the month');
            }
                $payment->amount_paid= $payment->amount_paid + $request->amount_paid;
                $payment->date_paid = Carbon::now();
                $payment->payment_purpose = 'loan payback';
                $payment->payment_status = 1;
                $payment->admin_incharge = Auth()->user()->name;
                $payment->loan->status = 2;
                $payment->loan->sum_of_allpayback = $payment->loan->sum_of_allpayback + $request->amount_paid;
            if($payment->client->status == 'in tenure' ){
                $payment->loan->actual_profit = $payment->loan->actual_profit + $intrest_permonth;
                $payment->profit = $intrest_permonth;
            }
            if($payment->loan->updated_at->format('m,Y') == date('m,Y')){
                $payment->loan->monthly_profit = $payment->loan->monthly_profit + $intrest_permonth;
                }
            else{
                $payment->loan->monthly_profit = $intrest_permonth;
                }
            if($payment->loan->updated_at->format('Y') == date('Y')){
                $payment->loan->yearly_profit = $payment->loan->yearly_profit + $intrest_permonth;
                }
            else{
                    $payment->loan->yearly_profit = $intrest_permonth;
                }
                $payment->client->status = 'out of tenure';
                $payment->save();
                $payment->loan->save();
                $payment->client->save();

            $paymentimes = Payment::where('loan_id',$id)->where('payment_status',1)->count();

            $data = [
                'client_no'=> $payment->client->client_no,
                'name'=> $payment->client->name,
                'phone'=> $payment->client->phone,
                'total_payback'=> $payment->loan->total_payback,
                'tenure'=> $payment->loan->tenure,
                'loan_amount' => $payment->loan->loan_amount,
                'loan_duration' => $payment->loan->loan_duration,
                'intrest' => $payment->loan->intrest, 
                'disbursement_date' => $payment->loan->disbursement_date, 
                'total_amountpaid' => $payment->loan->sum_of_allpayback,
                'actual_profit' => $payment->loan->actual_profit,
                'amount_paid' => $request->amount_paid,
                'monthly_payback' => $payment->payback_permonth,
                'no_of_time_paid' => $paymentimes,
                'subject'=> 'New/Last Payment made,',
                'type'=> 'payment completed',
                'date_paid' => $payment->date_paid,
                'admin_incharge'=> Auth()->user()->name,
                'date'=> Carbon::now(),

            ];
            //Mail::to('info@agapeglobal.com.ng')->send(new AgapeEmail($data));
            //Mail::to('theconsode@gmail.com')->send(new AgapeEmail($data));
            return back()->with('message', 'Payback Completed, Congratulations!');
        }
        // End of condition for completing payback, Out of Tenure
        
    }
// End of all payback function    

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