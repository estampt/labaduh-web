<?php
namespace App\Notifications;
use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
class PendingVendorApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public Vendor $vendor) {}
    public function via($notifiable): array { return ['database']; }
    public function toArray($notifiable): array {
        return ['type'=>'vendor_pending_approval','vendor_id'=>$this->vendor->id,'vendor_name'=>$this->vendor->name,'vendor_email'=>$this->vendor->email,'approval_status'=>$this->vendor->approval_status,'created_at'=>$this->vendor->created_at?->toISOString()];
    }
}
