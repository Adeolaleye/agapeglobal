@extends('layouts.main') 
@section('title','Loan History') 
@section('content')
<div class="container-fluid">
    <div class="page-title">
        <div class="row">
            <div class="col-6">
                <h5>
                    <a href="{{ route('viewclient',['id' => $loanhistory->client->id, 'branchID' => $branchID, 'viewType' => $viewType]) }}" data-bs-toggle="tooltip" title="View Client Details">
                        {{ $loanhistory->client->name }}</a>'s Loan Payment History <br> 
                    <span class="f-14 font-bold text-warning">Payment made {{ $counter }} times</span></h5>
                    <div class="card-header-right">
                    </div>
            </div>
            <div class="col-6">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('home')  }}"> <i data-feather="home"></i></a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('clients')  }}">All Clients</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('viewclient',['id' => $loanhistory->client->id, 'branchID' => $branchID, 'viewType' => $viewType]) }}">View Client</a>
                    </li>
                    <li class="breadcrumb-item">Pay History</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
              <div class="card-header">
                <div class="row">
                    @if ($viewType == 'BusinessOffice')
                    <div class="col-md-8 col-sm-12">
                        <span>Initial Duration is {{ $loanhistory->duration_in_days }} days</span><br>
                        <span>Loan Total Amount is #{{ number_format($loanhistory->loan_amount)}}</span><br>
                        <span>Loan Daily Payback is #{{ number_format($loanhistory->daily_payback)}}</span><br>
                        <span>Total sum paid is #{{ number_format($loanhistory->sum_of_allpayback )}}</span>
                    </div>
                    @else
                    <div class="col-md-8 col-sm-12">
                        <span>Initial Tenure is {{ $loanhistory->tenure }}</span><br>
                        <span>Loan Total Amount is #{{ number_format($loanhistory->loan_amount)}}</span><br>
                        <span>Loan Expected Payback is #{{ number_format($loanhistory->total_payback)}}</span><br>
                        <span>Total sum paid is #{{ number_format($loanhistory->sum_of_allpayback )}}</span>
                    </div>
                    @endif
                </div>
              </div>
              <div class="card-body">
                  @include('includes.alerts')
                  <div class="table-responsive">
                    <table class="display" id="basic-1">
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th>Payment No</th>
                          <th>Balance Brought Forward</th>
                          <th>Partial Pay</th>
                          <th>Total Amount Paid</th>
                          <th>Outstanding</th>
                          @if ($viewType == 'BusinessOffice')
                          <th>For day</th>
                          @else
                          <th>For the month of</th>
                          @endif
                        </tr>
                      </thead>
                      <tbody>
                        @php 
                        $i = 1;
                        @endphp
                          @foreach ($payhistorys as $payhistory )
                          <tr>
                              <td>{{ date('d,M Y h:i A', strtotime($payhistory->date_paid)) }}</td>
                              <td>{{ $i++ }}</td>
                              <td>{{ number_format($payhistory->bb_forward) }}</td>
                              <td>{{ number_format($payhistory->partial_pay) }}</td>
                              <td>{{ number_format($payhistory->amount_paid) }}</td>
                              <td>{{ number_format($payhistory->outstanding_payment) }}</td>
                              @if ($viewType == 'BusinessOffice')
                              <td>{{ date('jS F', strtotime($payhistory->next_due_date)) }}</td>
                              @else
                              <td>{{ date('M', strtotime($payhistory->next_due_date)) }}</td>
                              @endif
                          </tr>
                          @endforeach
                      </tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>
    </div>
  </div>
@endsection