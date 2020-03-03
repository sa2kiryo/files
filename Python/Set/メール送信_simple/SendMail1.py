#!/usr/bin/env python
# coding: utf-8

# In[14]:


#ステップ1｜ライブラリ
import win32com.client

#ステップ2｜Outlookのオブジェクト設定
outlook = win32com.client.Dispatch("Outlook.Application")
mymail = outlook.CreateItem(0) 

#ステップ3｜メールの設定
mymail.BodyFormat = 3 #リッチテキスト
mymail.To = "★★送信者のメールアドレス★★"
mymail.cc = "★★CCのメールアドレス★★"
mymail.Bcc = "★★BCCのメールアドレス★★"
mymail.Subject = "★★件名★★"
mymail.Body = "本文1行目" + "\n\n" + "本文3行目"

#添付ファイル（ファイルの場所を指定し直してね）
mymail.Attachments.Add ("C:\\Users\\Documents\\python\\mailSend\\book1.xlsx")

#ステップ4｜メール送信
mymail.Send()


# In[ ]:




